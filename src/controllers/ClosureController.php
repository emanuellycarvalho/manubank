<?php

declare(strict_types=1);

/**
 * Controlador de fechamento mensal.
 *
 * Consolida o caixa do mês (rollover + receitas - despesas efectivas),
 * regista os aportes de investimento e determina se o padrão foi mantido.
 *
 * Constantes de negócio:
 *   - Saldo ideal em conta: R$ 1.200,00
 *   - Investimento mínimo mensal: R$ 4.000,00
 */
final class ClosureController
{
    /** Saldo mínimo desejado em conta após aportes. */
    private const DEFAULT_TARGET_BALANCE = 1200.00;

    /** Soma mínima de aportes para o padrão não ser considerado quebrado. */
    private const DEFAULT_MIN_INVESTMENT = 4000.00;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ReimbursementController $reimbursements
    ) {
    }

    /**
     * Calcula o resumo financeiro de um mês.
     *
     * O rollover é o saldo acumulado histórico do Mercado Pago até ao início do mês.
     * Pode ser substituído por um valor manual através de `$rolloverOverride`.
     *
     * @return array{month_year: string, rollover: float, total_income: float, total_effective_expenses: float, available_cash: float, effective_expenses_by_category: array}
     */
    public function getMonthlySummary(string $monthYear, ?float $rolloverOverride = null): array
    {
        $rollover = $rolloverOverride ?? $this->calculateRollover($monthYear);

        $totalIncome = $this->sumIncome($monthYear);

        $expensesByCategory    = $this->reimbursements->getEffectiveExpenses($monthYear);
        $totalEffectiveExpenses = array_sum(array_column($expensesByCategory, 'effective_amount'));

        $availableCash = $rollover + $totalIncome - $totalEffectiveExpenses;

        return [
            'month_year'                    => $monthYear,
            'rollover'                      => round($rollover, 2),
            'total_income'                  => round($totalIncome, 2),
            'total_effective_expenses'      => round($totalEffectiveExpenses, 2),
            'available_cash'                => round($availableCash, 2),
            'effective_expenses_by_category' => $expensesByCategory,
        ];
    }

    /**
     * Persiste o fechamento do mês e os respectivos aportes de investimento.
     *
     * Idempotente: re-fechar o mesmo mês substitui os dados anteriores.
     *
     * @param array<int, array{objective: string, target_amount: float, actual_amount: float, is_extra_surplus: bool}> $investments
     *
     * @return int ID do registro em monthly_closures.
     *
     * @throws \PDOException Em caso de falha na DB.
     */
    public function saveClosure(string $monthYear, array $investments, ?float $rolloverOverride = null): int
    {
        $summary        = $this->getMonthlySummary($monthYear, $rolloverOverride);
        $availableCash  = $summary['available_cash'];
        $totalIncome    = $summary['total_income'];

        $totalInvested  = array_sum(array_column($investments, 'actual_amount'));
        $actualBalance  = $availableCash - $totalInvested;

        $surplusInvested = max(0.0, $actualBalance - self::DEFAULT_TARGET_BALANCE);

        $patternBroken = (
            $actualBalance < self::DEFAULT_TARGET_BALANCE ||
            $totalInvested < self::DEFAULT_MIN_INVESTMENT
        ) ? 1 : 0;

        $this->pdo->beginTransaction();

        try {
            // INSERT OR REPLACE preserva notes e ai_insights se já existir um registo com o mesmo month_year
            $upsert = $this->pdo->prepare(
                'INSERT OR REPLACE INTO monthly_closures
                     (month_year, total_income, target_balance, actual_balance, surplus_invested, pattern_broken)
                 VALUES
                     (:month_year, :total_income, :target_balance, :actual_balance, :surplus_invested, :pattern_broken)'
            );

            $upsert->execute([
                ':month_year'       => $monthYear,
                ':total_income'     => round($totalIncome, 2),
                ':target_balance'   => self::DEFAULT_TARGET_BALANCE,
                ':actual_balance'   => round($actualBalance, 2),
                ':surplus_invested' => round($surplusInvested, 2),
                ':pattern_broken'   => $patternBroken,
            ]);

            $closureId = (int) $this->pdo->lastInsertId();

            // Apagar alocações anteriores (re-fechamento idempotente)
            $this->pdo->prepare('DELETE FROM investment_allocations WHERE monthly_closure_id = :id')
                      ->execute([':id' => $closureId]);

            // Inserir alocações
            $insertAlloc = $this->pdo->prepare(
                'INSERT INTO investment_allocations
                     (monthly_closure_id, objective, target_amount, actual_amount, is_extra_surplus)
                 VALUES
                     (:monthly_closure_id, :objective, :target_amount, :actual_amount, :is_extra_surplus)'
            );

            foreach ($investments as $inv) {
                $insertAlloc->execute([
                    ':monthly_closure_id' => $closureId,
                    ':objective'          => $inv['objective'],
                    ':target_amount'      => (float) $inv['target_amount'],
                    ':actual_amount'      => (float) $inv['actual_amount'],
                    ':is_extra_surplus'   => ($inv['is_extra_surplus'] ?? false) ? 1 : 0,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $closureId;
    }

    /**
     * Recupera o fechamento de um mês com as respectivas alocações.
     *
     * Retorna null se ainda não existir fechamento para o mês.
     *
     * @return array{id: int, month_year: string, total_income: float, target_balance: float, actual_balance: float, surplus_invested: float, pattern_broken: bool, notes: string|null, ai_insights: string|null, allocations: array}|null
     */
    public function getClosure(string $monthYear): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mc.id, mc.month_year, mc.total_income, mc.target_balance,
                    mc.actual_balance, mc.surplus_invested, mc.pattern_broken,
                    mc.notes, mc.ai_insights,
                    ia.id AS alloc_id, ia.objective, ia.target_amount,
                    ia.actual_amount, ia.is_extra_surplus
             FROM monthly_closures mc
             LEFT JOIN investment_allocations ia ON ia.monthly_closure_id = mc.id
             WHERE mc.month_year = :month_year'
        );

        $stmt->execute([':month_year' => $monthYear]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return null;
        }

        $first = $rows[0];

        $closure = [
            'id'              => (int) $first['id'],
            'month_year'      => $first['month_year'],
            'total_income'    => (float) $first['total_income'],
            'target_balance'  => (float) $first['target_balance'],
            'actual_balance'  => (float) $first['actual_balance'],
            'surplus_invested' => (float) $first['surplus_invested'],
            'pattern_broken'  => (bool) $first['pattern_broken'],
            'notes'           => $first['notes'],
            'ai_insights'     => $first['ai_insights'],
            'allocations'     => [],
        ];

        foreach ($rows as $row) {
            if ($row['alloc_id'] === null) {
                continue;
            }

            $closure['allocations'][] = [
                'id'              => (int) $row['alloc_id'],
                'objective'       => $row['objective'],
                'target_amount'   => (float) $row['target_amount'],
                'actual_amount'   => (float) $row['actual_amount'],
                'is_extra_surplus' => (bool) $row['is_extra_surplus'],
            ];
        }

        return $closure;
    }

    // ---------------------------------------------------------------------------
    // Métodos privados
    // ---------------------------------------------------------------------------

    /**
     * Calcula o rollover como o saldo acumulado do Mercado Pago até ao mês anterior.
     *
     * Considera todos os meses anteriores a `$monthYear`, somando entradas
     * e subtraindo saídas do origin = 'MercadoPago'.
     */
    private function calculateRollover(string $monthYear): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type = 'entrada' THEN amount ELSE -amount END), 0)
             FROM transactions
             WHERE origin = 'MercadoPago'
               AND month_year < :month_year"
        );

        $stmt->execute([':month_year' => $monthYear]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Soma todas as receitas (type = 'entrada') de um mês, independente da origem.
     */
    private function sumIncome(string $monthYear): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM transactions
             WHERE type = 'entrada'
               AND month_year = :month_year"
        );

        $stmt->execute([':month_year' => $monthYear]);

        return (float) $stmt->fetchColumn();
    }
}

<?php

declare(strict_types=1);

/**
 * Controlador de reembolsos, rateios e empréstimos.
 *
 * Gere o ciclo de vida de uma pendência de reembolso:
 *   1. Criar o claim (createClaim) associando-o a uma despesa.
 *   2. Registar pagamentos recebidos (registerPayment), actualizando o status.
 *   3. Consultar despesas efectivas por categoria (getEffectiveExpenses).
 *   4. Listar claims em aberto ou parcialmente pagos (getActiveClaims).
 */
final class ReimbursementController
{
    /** Nomes aceites para a categoria de empréstimos/reembolsos (primeiro encontrado prevalece). */
    private const REIMBURSEMENT_CATEGORY_NAMES = [
        'Reembolso/Terceiros',
        'Empréstimo/Reembolsos',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Cria um novo claim de reembolso associado a uma transação de despesa.
     *
     * @throws \InvalidArgumentException Se a transação não existir ou o valor for inválido.
     *
     * @return int ID do claim criado.
     */
    public function createClaim(int $transactionId, float $expectedAmount, string $description): int
    {
        if ($expectedAmount <= 0.0) {
            throw new \InvalidArgumentException('O valor esperado deve ser positivo.');
        }

        $check = $this->pdo->prepare('SELECT id FROM transactions WHERE id = :id LIMIT 1');
        $check->execute([':id' => $transactionId]);

        if ($check->fetch() === false) {
            throw new \InvalidArgumentException("Transação #{$transactionId} não encontrada.");
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO reimbursement_claims (transaction_id, expected_amount, description, status)
                 VALUES (:transaction_id, :expected_amount, :description, 'Aberto')"
            );

            $stmt->execute([
                ':transaction_id'  => $transactionId,
                ':expected_amount' => $expectedAmount,
                ':description'     => $description,
            ]);

            $claimId = (int) $this->pdo->lastInsertId();
            $this->assignReimbursementCategory($transactionId);

            $this->pdo->commit();

            return $claimId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Regista pagamentos recebidos para um conjunto de claims e actualiza os seus status.
     *
     * @param int                                                    $incomeTransactionId ID da transação de receita (Pix recebido).
     * @param array<int, array{claim_id: int, paid_amount: float}>   $allocations         Alocações por claim.
     *
     * @throws \InvalidArgumentException Se um claim não existir.
     * @throws \PDOException             Em caso de falha na DB.
     */
    public function registerPayment(int $incomeTransactionId, array $allocations): void
    {
        $this->pdo->beginTransaction();

        try {
            $insertPayment = $this->pdo->prepare(
                'INSERT INTO reimbursement_payments (claim_id, income_transaction_id, paid_amount)
                 VALUES (:claim_id, :income_transaction_id, :paid_amount)'
            );

            $sumPaid = $this->pdo->prepare(
                'SELECT COALESCE(SUM(paid_amount), 0) FROM reimbursement_payments WHERE claim_id = :claim_id'
            );

            $getExpected = $this->pdo->prepare(
                'SELECT expected_amount FROM reimbursement_claims WHERE id = :id LIMIT 1'
            );

            $updateStatus = $this->pdo->prepare(
                'UPDATE reimbursement_claims SET status = :status WHERE id = :id'
            );

            foreach ($allocations as $allocation) {
                $claimId   = (int) $allocation['claim_id'];
                $paidAmt   = (float) $allocation['paid_amount'];

                // Verificar existência do claim
                $getExpected->execute([':id' => $claimId]);
                $row = $getExpected->fetch();

                if ($row === false) {
                    throw new \InvalidArgumentException("Claim #{$claimId} não encontrado.");
                }

                $expectedAmount = (float) $row['expected_amount'];

                // Inserir pagamento
                $insertPayment->execute([
                    ':claim_id'              => $claimId,
                    ':income_transaction_id' => $incomeTransactionId,
                    ':paid_amount'           => $paidAmt,
                ]);

                // Recalcular total pago
                $sumPaid->execute([':claim_id' => $claimId]);
                $totalPaid = (float) $sumPaid->fetchColumn();

                // Actualizar status
                $status = $totalPaid >= $expectedAmount ? 'Quitado' : 'Parcial';
                $updateStatus->execute([':status' => $status, ':id' => $claimId]);
            }

            $this->assignReimbursementCategory($incomeTransactionId);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Retorna as despesas efectivas agrupadas por categoria para um mês.
     *
     * O valor efectivo de cada transação é descontado do valor do claim associado,
     * caso exista, reflectindo o custo real após reembolsos.
     *
     * @return array<int, array{category_id: int, category_name: string, color: string, effective_amount: float}>
     */
    public function getEffectiveExpenses(string $monthYear): array
    {
        $excludeSql = InternalTransferService::sqlExcludeFromTotals('t');

        $stmt = $this->pdo->prepare(
            "SELECT
                c.id   AS category_id,
                c.name AS category_name,
                c.color,
                SUM(t.amount - COALESCE(rc.expected_amount, 0)) AS effective_amount
             FROM transactions t
             JOIN categories c ON c.id = t.category_id
             LEFT JOIN reimbursement_claims rc ON rc.transaction_id = t.id
             WHERE t.type = 'saída'
               AND t.month_year = :month_year
             {$excludeSql}
             GROUP BY c.id, c.name, c.color
             ORDER BY effective_amount DESC"
        );

        $stmt->execute([':month_year' => $monthYear]);
        $rows = $stmt->fetchAll();

        return array_map(static fn(array $row): array => [
            'category_id'    => (int) $row['category_id'],
            'category_name'  => $row['category_name'],
            'color'          => $row['color'],
            'effective_amount' => (float) $row['effective_amount'],
        ], $rows);
    }

    /**
     * Lista todos os claims com status 'Aberto' ou 'Parcial', com info da transação original.
     *
     * @return array<int, array{
     *   id: int,
     *   description: string,
     *   expected_amount: float,
     *   paid_amount: float,
     *   outstanding_amount: float,
     *   status: string,
     *   date: string,
     *   translated_description: string,
     *   month_year: string
     * }>
     */
    public function getActiveClaims(): array
    {
        $stmt = $this->pdo->query(
            "SELECT rc.id, rc.description, rc.expected_amount, rc.status,
                    t.date, t.translated_description, t.month_year,
                    COALESCE((
                        SELECT SUM(rp.paid_amount)
                        FROM reimbursement_payments rp
                        WHERE rp.claim_id = rc.id
                    ), 0) AS paid_amount
             FROM reimbursement_claims rc
             JOIN transactions t ON t.id = rc.transaction_id
             WHERE rc.status IN ('Aberto', 'Parcial')
             ORDER BY t.date DESC"
        );

        $rows = $stmt->fetchAll();

        return array_map(static function (array $row): array {
            $expected    = (float) $row['expected_amount'];
            $paid        = (float) $row['paid_amount'];
            $outstanding = max(0.0, round($expected - $paid, 2));

            return [
                'id'                     => (int) $row['id'],
                'description'            => $row['description'],
                'expected_amount'        => round($expected, 2),
                'paid_amount'            => round($paid, 2),
                'outstanding_amount'     => $outstanding,
                'status'                 => $row['status'],
                'date'                   => $row['date'],
                'translated_description' => $row['translated_description'],
                'month_year'             => $row['month_year'],
            ];
        }, $rows);
    }

    /**
     * Resumo de reembolsos para o dashboard (pendente vs. quitado).
     *
     * @return array{
     *   pending_amount: float,
     *   settled_amount: float,
     *   claims: array<int, array{
     *     id: int,
     *     description: string,
     *     expected_amount: float,
     *     paid_amount: float,
     *     outstanding_amount: float,
     *     status: string
     *   }>
     * }
     */
    public function getDashboardSummary(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                rc.id,
                rc.description,
                rc.expected_amount,
                rc.status,
                COALESCE((
                    SELECT SUM(rp.paid_amount)
                    FROM reimbursement_payments rp
                    WHERE rp.claim_id = rc.id
                ), 0) AS paid_amount
             FROM reimbursement_claims rc
             ORDER BY rc.id DESC"
        );

        $claims         = [];
        $pendingAmount  = 0.0;
        $settledAmount  = 0.0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $expected    = (float) $row['expected_amount'];
            $paid        = (float) $row['paid_amount'];
            $status      = (string) $row['status'];
            $outstanding = max(0.0, round($expected - $paid, 2));

            if ($status === 'Quitado') {
                $settledAmount += $expected;
            } else {
                $pendingAmount += $outstanding;
            }

            $claims[] = [
                'id'                  => (int) $row['id'],
                'description'         => (string) $row['description'],
                'expected_amount'     => round($expected, 2),
                'paid_amount'         => round($paid, 2),
                'outstanding_amount'  => $outstanding,
                'status'              => $status,
            ];
        }

        return [
            'pending_amount' => round($pendingAmount, 2),
            'settled_amount' => round($settledAmount, 2),
            'claims'         => $claims,
        ];
    }

    /**
     * Atribui a categoria de reembolsos/empréstimos a uma transação.
     */
    private function assignReimbursementCategory(int $transactionId): void
    {
        $categoryId = $this->resolveReimbursementCategoryId();

        if ($categoryId === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE transactions SET category_id = :category_id WHERE id = :id'
        );
        $stmt->execute([
            ':category_id' => $categoryId,
            ':id'          => $transactionId,
        ]);
    }

    /**
     * Resolve o ID da categoria de reembolsos por nome (lista de aliases).
     */
    private function resolveReimbursementCategoryId(): ?int
    {
        $placeholders = implode(',', array_fill(0, count(self::REIMBURSEMENT_CATEGORY_NAMES), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT id, name FROM categories
             WHERE is_active = 1 AND name IN ({$placeholders})"
        );
        $stmt->execute(self::REIMBURSEMENT_CATEGORY_NAMES);

        $byName = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $byName[$row['name']] = (int) $row['id'];
        }

        foreach (self::REIMBURSEMENT_CATEGORY_NAMES as $name) {
            if (isset($byName[$name])) {
                return $byName[$name];
            }
        }

        return null;
    }
}

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

        $stmt = $this->pdo->prepare(
            "INSERT INTO reimbursement_claims (transaction_id, expected_amount, description, status)
             VALUES (:transaction_id, :expected_amount, :description, 'Aberto')"
        );

        $stmt->execute([
            ':transaction_id'  => $transactionId,
            ':expected_amount' => $expectedAmount,
            ':description'     => $description,
        ]);

        return (int) $this->pdo->lastInsertId();
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
     * @return array<int, array{id: int, description: string, expected_amount: float, status: string, date: string, translated_description: string, month_year: string}>
     */
    public function getActiveClaims(): array
    {
        $stmt = $this->pdo->query(
            "SELECT rc.id, rc.description, rc.expected_amount, rc.status,
                    t.date, t.translated_description, t.month_year
             FROM reimbursement_claims rc
             JOIN transactions t ON t.id = rc.transaction_id
             WHERE rc.status IN ('Aberto', 'Parcial')
             ORDER BY t.date DESC"
        );

        $rows = $stmt->fetchAll();

        return array_map(static fn(array $row): array => [
            'id'                     => (int) $row['id'],
            'description'            => $row['description'],
            'expected_amount'        => (float) $row['expected_amount'],
            'status'                 => $row['status'],
            'date'                   => $row['date'],
            'translated_description' => $row['translated_description'],
            'month_year'             => $row['month_year'],
        ], $rows);
    }
}

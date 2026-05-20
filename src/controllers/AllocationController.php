<?php

declare(strict_types=1);

/**
 * CRUD de alocações / contas de investimento (consolidado).
 */
final class AllocationController
{
    private const AMOUNT_EPSILON = 0.005;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?InvestmentController $investmentController = null,
    ) {
    }

    private function investments(): InvestmentController
    {
        return $this->investmentController ?? new InvestmentController($this->pdo);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT
                a.id,
                a.objective_id,
                o.name AS objective_name,
                a.bank,
                a.type,
                a.liquidity,
                a.amount,
                a.priority,
                a.cdi_percentage,
                a.monthly_rate,
                a.yearly_rate,
                a.description
             FROM investment_allocations a
             LEFT JOIN investment_objectives o ON o.id = a.objective_id
             ORDER BY a.priority IS NULL, a.priority ASC, a.bank ASC, a.id ASC'
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => $this->mapRow($row), $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function getById(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                a.id,
                a.objective_id,
                o.name AS objective_name,
                a.bank,
                a.type,
                a.liquidity,
                a.amount,
                a.priority,
                a.cdi_percentage,
                a.monthly_rate,
                a.yearly_rate,
                a.description
             FROM investment_allocations a
             LEFT JOIN investment_objectives o ON o.id = a.objective_id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \InvalidArgumentException("Alocação #{$id} não encontrada.");
        }

        return $this->mapRow($row);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $data = $this->validatePayload($payload);

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO investment_allocations (
                    objective_id, bank, type, liquidity, amount, priority,
                    cdi_percentage, monthly_rate, yearly_rate, description
                 ) VALUES (
                    :objective_id, :bank, :type, :liquidity, :amount, :priority,
                    :cdi_percentage, :monthly_rate, :yearly_rate, :description
                 )'
            );
            $stmt->execute($data);

            $id = (int) $this->pdo->lastInsertId();

            if ($data[':objective_id'] !== null) {
                $this->recordConsolidatedEntry(
                    (int) $data[':objective_id'],
                    'entrada',
                    (float) $data[':amount'],
                    (string) $data[':bank'],
                    'Saldo inicial consolidado',
                );
            }

            $this->pdo->commit();

            return $this->getById($id);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function update(int $id, array $payload): array
    {
        $before = $this->getById($id);
        $data = $this->validatePayload($payload);

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE investment_allocations SET
                    objective_id = :objective_id,
                    bank = :bank,
                    type = :type,
                    liquidity = :liquidity,
                    amount = :amount,
                    priority = :priority,
                    cdi_percentage = :cdi_percentage,
                    monthly_rate = :monthly_rate,
                    yearly_rate = :yearly_rate,
                    description = :description
                 WHERE id = :id'
            );
            $stmt->execute([...$data, ':id' => $id]);

            $this->syncObjectiveEntriesAfterAllocationChange($before, $data);

            $this->pdo->commit();

            return $this->getById($id);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $before = $this->getById($id);

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('DELETE FROM investment_allocations WHERE id = :id');
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() === 0) {
                throw new \InvalidArgumentException("Alocação #{$id} não encontrada.");
            }

            if ($before['objective_id'] !== null) {
                $this->recordConsolidatedEntry(
                    $before['objective_id'],
                    'saída',
                    $before['amount'],
                    $before['bank'],
                    'Remoção consolidado',
                );
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Lançamento automático no objetivo quando o consolidado muda (unilateral).
     *
     * @param array<string, mixed> $validatedRow Dados de validatePayload()
     */
    private function syncObjectiveEntriesAfterAllocationChange(array $before, array $validatedRow): void
    {
        $beforeObjectiveId = $before['objective_id'];
        $afterObjectiveId = $validatedRow[':objective_id'];
        $beforeAmount = (float) $before['amount'];
        $afterAmount = (float) $validatedRow[':amount'];
        $bank = (string) $validatedRow[':bank'];

        if ($beforeObjectiveId === $afterObjectiveId && $beforeObjectiveId !== null) {
            $this->recordAmountDelta(
                $beforeObjectiveId,
                $beforeAmount,
                $afterAmount,
                $bank,
            );

            return;
        }

        if ($beforeObjectiveId !== null) {
            $this->recordConsolidatedEntry(
                $beforeObjectiveId,
                'saída',
                $beforeAmount,
                (string) $before['bank'],
                'Transferência consolidado',
            );
        }

        if ($afterObjectiveId !== null) {
            $this->recordConsolidatedEntry(
                (int) $afterObjectiveId,
                'entrada',
                $afterAmount,
                $bank,
                $beforeObjectiveId === null ? 'Saldo inicial consolidado' : 'Transferência consolidado',
            );
        }
    }

    private function recordAmountDelta(int $objectiveId, float $beforeAmount, float $afterAmount, string $bank): void
    {
        $delta = round($afterAmount - $beforeAmount, 2);

        if (abs($delta) < self::AMOUNT_EPSILON) {
            return;
        }

        $this->recordConsolidatedEntry(
            $objectiveId,
            $delta > 0 ? 'entrada' : 'saída',
            abs($delta),
            $bank,
            'Ajuste consolidado',
        );
    }

    private function recordConsolidatedEntry(
        int $objectiveId,
        string $type,
        float $amount,
        string $bank,
        string $reason,
    ): void {
        if ($amount <= 0.0) {
            return;
        }

        $bankLabel = trim($bank) !== '' ? trim($bank) : 'Conta';
        $description = sprintf('%s (%s)', $reason, $bankLabel);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $this->investments()->addEntry($objectiveId, $type, $amount, $today, $description);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function validatePayload(array $payload): array
    {
        $bank = trim((string) ($payload['bank'] ?? ''));
        if ($bank === '') {
            throw new \InvalidArgumentException("Campo 'bank' é obrigatório.");
        }

        $amount = isset($payload['amount']) ? (float) $payload['amount'] : null;
        if ($amount === null || $amount <= 0) {
            throw new \InvalidArgumentException("Campo 'amount' deve ser um número positivo.");
        }

        $objectiveId = $payload['objective_id'] ?? null;
        if ($objectiveId !== null && $objectiveId !== '') {
            $objectiveId = (int) $objectiveId;
            if ($objectiveId <= 0 || !$this->objectiveExists($objectiveId)) {
                throw new \InvalidArgumentException("Finalidade (objective_id) inválida.");
            }
        } else {
            $objectiveId = null;
        }

        $priority = $payload['priority'] ?? null;
        if ($priority !== null && $priority !== '') {
            $priority = (int) $priority;
            if ($priority < 1 || $priority > 5) {
                throw new \InvalidArgumentException("Campo 'priority' deve estar entre 1 e 5.");
            }
        } else {
            $priority = null;
        }

        return [
            ':objective_id'   => $objectiveId,
            ':bank'           => $bank,
            ':type'           => $this->nullableString($payload['type'] ?? null),
            ':liquidity'      => $this->nullableString($payload['liquidity'] ?? null),
            ':amount'         => round($amount, 2),
            ':priority'       => $priority,
            ':cdi_percentage' => $this->nullableFloat($payload['cdi_percentage'] ?? null),
            ':monthly_rate'   => $this->nullableFloat($payload['monthly_rate'] ?? null),
            ':yearly_rate'    => $this->nullableFloat($payload['yearly_rate'] ?? null),
            ':description'    => $this->nullableString($payload['description'] ?? null),
        ];
    }

    private function objectiveExists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM investment_objectives WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 4);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        return [
            'id'              => (int) $row['id'],
            'objective_id'    => $row['objective_id'] !== null ? (int) $row['objective_id'] : null,
            'objective_name'  => $row['objective_name'] !== null ? (string) $row['objective_name'] : null,
            'bank'            => (string) $row['bank'],
            'type'            => $row['type'],
            'liquidity'       => $row['liquidity'],
            'amount'          => (float) $row['amount'],
            'priority'        => $row['priority'] !== null ? (int) $row['priority'] : null,
            'cdi_percentage'  => $row['cdi_percentage'] !== null ? (float) $row['cdi_percentage'] : null,
            'monthly_rate'    => $row['monthly_rate'] !== null ? (float) $row['monthly_rate'] : null,
            'yearly_rate'     => $row['yearly_rate'] !== null ? (float) $row['yearly_rate'] : null,
            'description'     => $row['description'],
        ];
    }
}

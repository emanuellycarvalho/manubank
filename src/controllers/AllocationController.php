<?php

declare(strict_types=1);

/**
 * CRUD de alocações / contas de investimento (consolidado).
 */
final class AllocationController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
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

        return $this->getById((int) $this->pdo->lastInsertId());
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function update(int $id, array $payload): array
    {
        $this->getById($id);
        $data = $this->validatePayload($payload);

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

        return $this->getById($id);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM investment_allocations WHERE id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            throw new \InvalidArgumentException("Alocação #{$id} não encontrada.");
        }
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

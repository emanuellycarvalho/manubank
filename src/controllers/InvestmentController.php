<?php

declare(strict_types=1);

/**
 * Controlador de objetivos de investimento e aportes.
 *
 * Gere metas de longo prazo (investment_objectives) e lançamentos
 * de entrada/saída (investment_entries), com métricas derivadas.
 */
final class InvestmentController
{
    private const VALID_ENTRY_TYPES = ['entrada', 'saída'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Cria um novo objetivo de investimento.
     *
     * @throws \InvalidArgumentException Se os parâmetros forem inválidos.
     *
     * @return int ID do objetivo criado.
     */
    public function createObjective(string $name, float $targetAmount, string $endDate): int
    {
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException('O nome do objetivo é obrigatório.');
        }

        if ($targetAmount <= 0.0) {
            throw new \InvalidArgumentException('A meta financeira deve ser positiva.');
        }

        $this->parseDate($endDate);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO investment_objectives (name, target_amount, end_date, created_at)
                 VALUES (:name, :target_amount, :end_date, :created_at)'
            );

            $stmt->execute([
                ':name'          => $name,
                ':target_amount' => $targetAmount,
                ':end_date'      => $endDate,
                ':created_at'    => $this->today()->format('Y-m-d'),
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            throw new \RuntimeException('Erro ao criar objetivo: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Regista um aporte (entrada ou saída) num objetivo.
     *
     * @throws \InvalidArgumentException Se os parâmetros forem inválidos ou o objetivo não existir.
     *
     * @return int ID do lançamento criado.
     */
    public function addEntry(
        int $objectiveId,
        string $type,
        float $amount,
        string $date,
        string $description = ''
    ): int {
        if (!$this->objectiveExists($objectiveId)) {
            throw new \InvalidArgumentException("Objetivo #{$objectiveId} não encontrado.");
        }

        if (!in_array($type, self::VALID_ENTRY_TYPES, true)) {
            throw new \InvalidArgumentException("Tipo inválido. Use 'entrada' ou 'saída'.");
        }

        if ($amount <= 0.0) {
            throw new \InvalidArgumentException('O valor do aporte deve ser positivo.');
        }

        $this->parseDate($date);

        $descriptionValue = trim($description) !== '' ? trim($description) : null;

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO investment_entries (objective_id, type, amount, date, description)
                 VALUES (:objective_id, :type, :amount, :date, :description)'
            );

            $stmt->execute([
                ':objective_id' => $objectiveId,
                ':type'         => $type,
                ':amount'       => $amount,
                ':date'         => $date,
                ':description'  => $descriptionValue,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            throw new \RuntimeException('Erro ao registar aporte: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Actualiza um objetivo existente.
     *
     * @throws \InvalidArgumentException Se os parâmetros forem inválidos ou o objetivo não existir.
     */
    public function updateObjective(int $id, string $name, float $targetAmount, string $endDate): void
    {
        if (!$this->objectiveExists($id)) {
            throw new \InvalidArgumentException("Objetivo #{$id} não encontrado.");
        }

        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException('O nome do objetivo é obrigatório.');
        }

        if ($targetAmount <= 0.0) {
            throw new \InvalidArgumentException('A meta financeira deve ser positiva.');
        }

        $this->parseDate($endDate);

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE investment_objectives
                 SET name = :name, target_amount = :target_amount, end_date = :end_date
                 WHERE id = :id'
            );

            $stmt->execute([
                ':id'            => $id,
                ':name'          => $name,
                ':target_amount' => $targetAmount,
                ':end_date'      => $endDate,
            ]);
        } catch (\PDOException $e) {
            throw new \RuntimeException('Erro ao actualizar objetivo: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Remove um objetivo e todos os lançamentos associados (CASCADE).
     *
     * @throws \InvalidArgumentException Se o objetivo não existir.
     */
    public function deleteObjective(int $id): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM investment_objectives WHERE id = :id');
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() === 0) {
                throw new \InvalidArgumentException("Objetivo #{$id} não encontrado.");
            }
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Erro ao remover objetivo: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Actualiza um lançamento existente.
     *
     * @throws \InvalidArgumentException Se os parâmetros forem inválidos ou o lançamento não existir.
     */
    public function updateEntry(
        int $entryId,
        string $type,
        float $amount,
        string $date,
        string $description = ''
    ): void {
        if (!in_array($type, self::VALID_ENTRY_TYPES, true)) {
            throw new \InvalidArgumentException("Tipo inválido. Use 'entrada' ou 'saída'.");
        }

        if ($amount <= 0.0) {
            throw new \InvalidArgumentException('O valor do aporte deve ser positivo.');
        }

        $this->parseDate($date);

        $descriptionValue = trim($description) !== '' ? trim($description) : null;

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE investment_entries
                 SET type = :type, amount = :amount, date = :date, description = :description
                 WHERE id = :id'
            );

            $stmt->execute([
                ':id'           => $entryId,
                ':type'         => $type,
                ':amount'       => $amount,
                ':date'         => $date,
                ':description'  => $descriptionValue,
            ]);

            if ($stmt->rowCount() === 0) {
                throw new \InvalidArgumentException("Entrada #{$entryId} não encontrada.");
            }
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Erro ao actualizar entrada: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Remove um lançamento de aporte.
     *
     * @throws \InvalidArgumentException Se o lançamento não existir.
     */
    public function deleteEntry(int $entryId): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM investment_entries WHERE id = :id');
            $stmt->execute([':id' => $entryId]);

            if ($stmt->rowCount() === 0) {
                throw new \InvalidArgumentException("Entrada #{$entryId} não encontrada.");
            }
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Erro ao remover entrada: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Lista todos os objetivos com métricas calculadas e histórico de lançamentos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getObjectivesWithMetrics(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT o.id, o.name, o.target_amount, o.end_date, o.created_at,
                        e.id AS entry_id, e.type, e.amount, e.date, e.description
                 FROM investment_objectives o
                 LEFT JOIN investment_entries e ON e.objective_id = o.id
                 ORDER BY o.id ASC, e.date DESC, e.id DESC'
            );

            $rows = $stmt->fetchAll();
        } catch (\PDOException $e) {
            throw new \RuntimeException('Erro ao consultar objetivos: ' . $e->getMessage(), 0, $e);
        }

        $grouped = [];

        foreach ($rows as $row) {
            $objectiveId = (int) $row['id'];

            if (!isset($grouped[$objectiveId])) {
                $grouped[$objectiveId] = [
                    'id'            => $objectiveId,
                    'name'          => (string) $row['name'],
                    'target_amount' => (float) $row['target_amount'],
                    'end_date'      => (string) $row['end_date'],
                    'created_at'    => (string) $row['created_at'],
                    'entries'       => [],
                ];
            }

            if ($row['entry_id'] !== null) {
                $grouped[$objectiveId]['entries'][] = [
                    'id'           => (int) $row['entry_id'],
                    'objective_id' => $objectiveId,
                    'type'         => (string) $row['type'],
                    'amount'       => (float) $row['amount'],
                    'date'         => (string) $row['date'],
                    'description'  => $row['description'] !== null ? (string) $row['description'] : null,
                ];
            }
        }

        $allocationTotals = $this->fetchAllocationTotalsByObjective();
        $today = $this->today();
        $result = [];

        foreach ($grouped as $objective) {
            // Valor acumulado = soma das contas no consolidado (fonte de verdade unilateral).
            $valorAcumulado = $allocationTotals[$objective['id']] ?? 0.0;

            $targetAmount = $objective['target_amount'];
            $porcentagemAlcancada = $targetAmount > 0.0
                ? round(($valorAcumulado / $targetAmount) * 100, 2)
                : 0.0;

            $referenceDate = $this->resolveReferenceDate($objective['entries'], $objective['created_at']);
            $mesesJuntando = max(1, $this->absoluteCalendarMonths($referenceDate, $today));

            $endDate = $this->parseDate($objective['end_date']);
            $tempoRestanteMeses = $endDate < $today
                ? 0
                : $this->calendarMonthsBetween($today, $endDate);

            $result[] = [
                'id'                        => $objective['id'],
                'name'                      => $objective['name'],
                'target_amount'             => $targetAmount,
                'end_date'                  => $objective['end_date'],
                'created_at'                => $objective['created_at'],
                'valor_acumulado'           => $valorAcumulado,
                'porcentagem_alcancada'     => $porcentagemAlcancada,
                'meses_juntando'            => $mesesJuntando,
                'investimento_mensal_medio' => round($valorAcumulado / $mesesJuntando, 2),
                'tempo_restante_meses'      => $tempoRestanteMeses,
                'historico'                 => $objective['entries'],
            ];
        }

        return $result;
    }

    /**
     * Determina a data de referência para o cálculo de meses_juntando.
     *
     * @param array<int, array<string, mixed>> $entries
     */
    private function resolveReferenceDate(array $entries, string $createdAt): DateTime
    {
        if ($entries === []) {
            return $this->parseDate($createdAt);
        }

        $earliest = $entries[0]['date'];

        foreach ($entries as $entry) {
            if ($entry['date'] < $earliest) {
                $earliest = $entry['date'];
            }
        }

        return $this->parseDate($earliest);
    }

    /**
     * @return array<int, float> objective_id => soma amount das alocações
     */
    private function fetchAllocationTotalsByObjective(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT objective_id, COALESCE(SUM(amount), 0) AS total
                 FROM investment_allocations
                 WHERE objective_id IS NOT NULL
                 GROUP BY objective_id'
            );
        } catch (\PDOException $e) {
            // Tabela pode não existir em bases legadas de testes.
            return [];
        }

        $totals = [];

        foreach ($stmt->fetchAll() as $row) {
            $totals[(int) $row['objective_id']] = round((float) $row['total'], 2);
        }

        return $totals;
    }

    private function objectiveExists(int $objectiveId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM investment_objectives WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $objectiveId]);

        return $stmt->fetch() !== false;
    }

    /**
     * Valida e devolve uma data no formato YYYY-MM-DD.
     *
     * @throws \InvalidArgumentException
     */
    private function parseDate(string $value): DateTime
    {
        $value = trim($value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches) === 1) {
            $value = $matches[1];
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);

        if ($date === false) {
            throw new \InvalidArgumentException("Data inválida: '{$value}'. Use o formato YYYY-MM-DD.");
        }

        $errors = DateTime::getLastErrors();

        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new \InvalidArgumentException("Data inválida: '{$value}'. Use o formato YYYY-MM-DD.");
        }

        $date->setTime(0, 0, 0);

        return $date;
    }

    private function today(): DateTime
    {
        $today = new DateTime('today');
        $today->setTime(0, 0, 0);

        return $today;
    }

    /**
     * Diferença absoluta em meses de calendário entre duas datas.
     */
    private function absoluteCalendarMonths(DateTime $a, DateTime $b): int
    {
        return $this->calendarMonthsBetween($a, $b);
    }

    /**
     * Meses de calendário de $from até $to (sempre >= 0 quando $to >= $from).
     */
    private function calendarMonthsBetween(DateTime $from, DateTime $to): int
    {
        $y1 = (int) $from->format('Y');
        $m1 = (int) $from->format('m');
        $y2 = (int) $to->format('Y');
        $m2 = (int) $to->format('m');

        return abs(($y2 - $y1) * 12 + ($m2 - $m1));
    }
}

<?php

declare(strict_types=1);

/**
 * Agregação de transações por período para gráficos do dashboard.
 */
final class ChartController
{
    private const GRANULARITIES = ['day', 'week', 'month', 'semester'];

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @return array{
     *   start_date: string,
     *   end_date: string,
     *   granularity: string,
     *   series: array<int, array{
     *     period_label: string,
     *     total_income: float,
     *     total_expenses: float,
     *     total_yield: float,
     *     transaction_count: int
     *   }>
     * }
     */
    public function getAggregatedSeries(string $startDate, string $endDate, string $granularity): array
    {
        $this->validateDate($startDate, 'start_date');
        $this->validateDate($endDate, 'end_date');

        if ($startDate > $endDate) {
            throw new InvalidArgumentException("'start_date' não pode ser posterior a 'end_date'.");
        }

        $granularity = strtolower(trim($granularity));
        if (!in_array($granularity, self::GRANULARITIES, true)) {
            throw new InvalidArgumentException(
                "Granularidade inválida. Use: " . implode(', ', self::GRANULARITIES) . '.'
            );
        }

        $periodExpr = $this->periodLabelExpression($granularity);

        $sql = <<<SQL
            SELECT
                {$periodExpr} AS period_label,
                COALESCE(SUM(CASE WHEN type = 'entrada' THEN amount ELSE 0 END), 0) AS total_income,
                COALESCE(SUM(CASE WHEN type = 'saída' THEN amount ELSE 0 END), 0) AS total_expenses,
                COALESCE(SUM(CASE WHEN type = 'rendimento' THEN amount ELSE 0 END), 0) AS total_yield,
                COUNT(*) AS transaction_count
            FROM transactions
            WHERE date >= :start_date AND date <= :end_date
            GROUP BY period_label
            ORDER BY period_label ASC
            SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date'   => $endDate,
        ]);

        $series = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $series[] = [
                'period_label'      => (string) $row['period_label'],
                'total_income'      => round((float) $row['total_income'], 2),
                'total_expenses'    => round((float) $row['total_expenses'], 2),
                'total_yield'       => round((float) $row['total_yield'], 2),
                'transaction_count' => (int) $row['transaction_count'],
            ];
        }

        return [
            'start_date'   => $startDate,
            'end_date'     => $endDate,
            'granularity'  => $granularity,
            'series'       => $series,
        ];
    }

    /**
     * Despesas (saída) agrupadas por categoria no intervalo de datas.
     *
     * @return array{
     *   start_date: string,
     *   end_date: string,
     *   categories: array<int, array{
     *     category_id: int,
     *     name: string,
     *     color: string,
     *     amount: float,
     *     transaction_count: int
     *   }>
     * }
     */
    public function getExpensesByCategory(string $startDate, string $endDate): array
    {
        $this->validateDate($startDate, 'start_date');
        $this->validateDate($endDate, 'end_date');

        if ($startDate > $endDate) {
            throw new InvalidArgumentException("'start_date' não pode ser posterior a 'end_date'.");
        }

        $sql = <<<'SQL'
            SELECT
                c.id AS category_id,
                c.name AS category_name,
                c.color AS category_color,
                COALESCE(SUM(t.amount), 0) AS total_amount,
                COUNT(*) AS transaction_count
            FROM transactions t
            INNER JOIN categories c ON c.id = t.category_id
            WHERE t.type = 'saída'
              AND t.date >= :start_date
              AND t.date <= :end_date
            GROUP BY c.id, c.name, c.color
            HAVING total_amount > 0
            ORDER BY total_amount DESC
            SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date'   => $endDate,
        ]);

        $categories = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categories[] = [
                'category_id'       => (int) $row['category_id'],
                'name'              => (string) $row['category_name'],
                'color'             => (string) $row['category_color'],
                'amount'            => round((float) $row['total_amount'], 2),
                'transaction_count' => (int) $row['transaction_count'],
            ];
        }

        return [
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'categories' => $categories,
        ];
    }

    /**
     * Despesas empilhadas por tipo de categoria (Fixo vs. Variável) ao longo do período.
     *
     * @return array{
     *   start_date: string,
     *   end_date: string,
     *   granularity: string,
     *   series: array<int, array{
     *     period_label: string,
     *     fixed: float,
     *     variable: float,
     *     neutral: float
     *   }>
     * }
     */
    public function getFixedVsVariableSeries(string $startDate, string $endDate, string $granularity): array
    {
        $this->validateDate($startDate, 'start_date');
        $this->validateDate($endDate, 'end_date');

        if ($startDate > $endDate) {
            throw new InvalidArgumentException("'start_date' não pode ser posterior a 'end_date'.");
        }

        $granularity = strtolower(trim($granularity));
        if (!in_array($granularity, self::GRANULARITIES, true)) {
            throw new InvalidArgumentException(
                "Granularidade inválida. Use: " . implode(', ', self::GRANULARITIES) . '.'
            );
        }

        $periodExpr = $this->periodLabelExpression($granularity);

        $sql = <<<SQL
            SELECT
                {$periodExpr} AS period_label,
                COALESCE(SUM(CASE WHEN c.type = 'Fixo' THEN t.amount ELSE 0 END), 0) AS fixed_amount,
                COALESCE(SUM(CASE WHEN c.type = 'Variável' THEN t.amount ELSE 0 END), 0) AS variable_amount,
                COALESCE(SUM(CASE WHEN c.type = 'Neutro' THEN t.amount ELSE 0 END), 0) AS neutral_amount
            FROM transactions t
            INNER JOIN categories c ON c.id = t.category_id
            WHERE t.type = 'saída'
              AND t.date >= :start_date
              AND t.date <= :end_date
            GROUP BY period_label
            ORDER BY period_label ASC
            SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date'   => $endDate,
        ]);

        $series = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $series[] = [
                'period_label' => (string) $row['period_label'],
                'fixed'          => round((float) $row['fixed_amount'], 2),
                'variable'       => round((float) $row['variable_amount'], 2),
                'neutral'        => round((float) $row['neutral_amount'], 2),
            ];
        }

        return [
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'granularity' => $granularity,
            'series'      => $series,
        ];
    }

    /**
     * Evolução de despesas de uma única categoria ao longo do período.
     *
     * @return array{
     *   start_date: string,
     *   end_date: string,
     *   granularity: string,
     *   category_id: int,
     *   category_name: string,
     *   category_color: string,
     *   series: array<int, array{period_label: string, amount: float}>
     * }
     */
    public function getCategoryEvolutionSeries(
        string $startDate,
        string $endDate,
        string $granularity,
        int $categoryId,
    ): array {
        $this->validateDate($startDate, 'start_date');
        $this->validateDate($endDate, 'end_date');

        if ($startDate > $endDate) {
            throw new InvalidArgumentException("'start_date' não pode ser posterior a 'end_date'.");
        }

        if ($categoryId <= 0) {
            throw new InvalidArgumentException("Parâmetro 'category_id' inválido.");
        }

        $granularity = strtolower(trim($granularity));
        if (!in_array($granularity, self::GRANULARITIES, true)) {
            throw new InvalidArgumentException(
                "Granularidade inválida. Use: " . implode(', ', self::GRANULARITIES) . '.'
            );
        }

        $catStmt = $this->pdo->prepare(
            'SELECT id, name, color FROM categories WHERE id = :id LIMIT 1'
        );
        $catStmt->execute([':id' => $categoryId]);
        $category = $catStmt->fetch(PDO::FETCH_ASSOC);

        if ($category === false) {
            throw new InvalidArgumentException("Categoria #{$categoryId} não encontrada.");
        }

        $periodExpr = $this->periodLabelExpression($granularity);

        $sql = <<<SQL
            SELECT
                {$periodExpr} AS period_label,
                COALESCE(SUM(t.amount), 0) AS total_amount,
                COUNT(*) AS transaction_count
            FROM transactions t
            WHERE t.type = 'saída'
              AND t.category_id = :category_id
              AND t.date >= :start_date
              AND t.date <= :end_date
            GROUP BY period_label
            ORDER BY period_label ASC
            SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':category_id' => $categoryId,
            ':start_date'  => $startDate,
            ':end_date'    => $endDate,
        ]);

        $series = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $series[] = [
                'period_label'      => (string) $row['period_label'],
                'amount'            => round((float) $row['total_amount'], 2),
                'transaction_count' => (int) $row['transaction_count'],
            ];
        }

        return [
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'granularity'    => $granularity,
            'category_id'    => $categoryId,
            'category_name'  => (string) $category['name'],
            'category_color' => (string) $category['color'],
            'series'         => $series,
        ];
    }

    private function validateDate(string $value, string $paramName): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException(
                "Parâmetro '{$paramName}' obrigatório no formato YYYY-MM-DD."
            );
        }
    }

    private function periodLabelExpression(string $granularity): string
    {
        return match ($granularity) {
            'day' => "strftime('%Y-%m-%d', date)",
            'week' => "strftime('%Y-%W', date)",
            'month' => "strftime('%Y-%m', date)",
            'semester' => "strftime('%Y', date) || '-S' || CASE WHEN CAST(strftime('%m', date) AS INTEGER) <= 6 THEN '1' ELSE '2' END",
            default => throw new InvalidArgumentException('Granularidade não suportada.'),
        };
    }
}

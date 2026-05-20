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

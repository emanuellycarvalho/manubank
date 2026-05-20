<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChartControllerTest extends TestCase
{
    private \PDO $pdo;
    private \ChartController $controller;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL, type TEXT NOT NULL, color TEXT NOT NULL, is_active INTEGER DEFAULT 1
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                date TEXT NOT NULL,
                origin TEXT NOT NULL,
                operation TEXT NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                raw_description TEXT NOT NULL,
                translated_description TEXT,
                installment_current INTEGER,
                installment_total INTEGER,
                month_year TEXT NOT NULL
            )
        SQL);

        $this->pdo->exec("INSERT INTO categories (name, type, color) VALUES ('Geral', 'Variável', '#000')");
        $catId = (int) $this->pdo->lastInsertId();

        $rows = [
            ['entrada',  '2026-02-10', 1000.00, '2026-02'],
            ['saída',    '2026-02-20',  200.00, '2026-02'],
            ['entrada',  '2026-03-05',  500.00, '2026-03'],
            ['saída',    '2026-03-15',  150.00, '2026-03'],
            ['rendimento','2026-03-20',  50.00, '2026-03'],
            ['entrada',  '2026-01-15',  999.00, '2026-01'],
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($rows as [$type, $date, $amount, $monthYear]) {
            $stmt->execute([$catId, $type, $date, 'Test', 'op', $amount, 'desc', $monthYear]);
        }

        $this->controller = new \ChartController($this->pdo);
    }

    public function testMonthAggregationReturnsTwoPeriods(): void
    {
        $result = $this->controller->getAggregatedSeries('2026-02-01', '2026-03-31', 'month');

        $this->assertSame('month', $result['granularity']);
        $this->assertCount(2, $result['series']);

        $feb = $result['series'][0];
        $this->assertSame('2026-02', $feb['period_label']);
        $this->assertEqualsWithDelta(1000.00, $feb['total_income'], 0.01);
        $this->assertEqualsWithDelta(200.00, $feb['total_expenses'], 0.01);

        $mar = $result['series'][1];
        $this->assertSame('2026-03', $mar['period_label']);
        $this->assertEqualsWithDelta(500.00, $mar['total_income'], 0.01);
        $this->assertEqualsWithDelta(150.00, $mar['total_expenses'], 0.01);
        $this->assertEqualsWithDelta(50.00, $mar['total_yield'], 0.01);
    }

    public function testDateFilterExcludesOutOfRangeTransactions(): void
    {
        $result = $this->controller->getAggregatedSeries('2026-02-01', '2026-02-28', 'month');

        $this->assertCount(1, $result['series']);
        $this->assertSame('2026-02', $result['series'][0]['period_label']);
        $this->assertEqualsWithDelta(1000.00, $result['series'][0]['total_income'], 0.01);
    }

    public function testInternalTransferCategoryExcludedFromTotals(): void
    {
        $this->pdo->exec(
            "INSERT INTO categories (name, type, color) VALUES ('Movimentação interna', 'Neutro', '#8A8F9E')"
        );
        $internalId = (int) $this->pdo->lastInsertId();
        $geralId    = (int) $this->pdo->query('SELECT id FROM categories WHERE name = \'Geral\'')->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$internalId, 'entrada', '2026-04-01', 'Test', 'Pix', 3000.00, 'self', '2026-04']);
        $stmt->execute([$internalId, 'saída', '2026-04-02', 'Test', 'Pix', 3000.00, 'self', '2026-04']);
        $stmt->execute([$geralId, 'entrada', '2026-04-03', 'Test', 'op', 100.00, 'salary', '2026-04']);

        $result = $this->controller->getAggregatedSeries('2026-04-01', '2026-04-30', 'month');

        $this->assertCount(1, $result['series']);
        $this->assertEqualsWithDelta(100.00, $result['series'][0]['total_income'], 0.01);
        $this->assertEqualsWithDelta(0.00, $result['series'][0]['total_expenses'], 0.01);
    }

    public function testSeriesOrderedChronologically(): void
    {
        $result = $this->controller->getAggregatedSeries('2026-01-01', '2026-03-31', 'month');
        $labels = array_column($result['series'], 'period_label');

        $this->assertSame(['2026-01', '2026-02', '2026-03'], $labels);
    }

    #[DataProvider('granularityProvider')]
    public function testGranularityPeriodLabels(string $granularity, string $date, string $expectedLabel): void
    {
        $this->pdo->exec('DELETE FROM transactions');
        $catId = (int) $this->pdo->query('SELECT id FROM categories LIMIT 1')->fetchColumn();

        $this->pdo->exec(
            "INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES ({$catId}, 'entrada', '{$date}', 'Test', 'op', 10.00, 'desc', '2026-06')"
        );

        $result = $this->controller->getAggregatedSeries($date, $date, $granularity);

        $this->assertCount(1, $result['series']);
        $this->assertSame($expectedLabel, $result['series'][0]['period_label']);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function granularityProvider(): array
    {
        return [
            'day' => ['day', '2026-06-15', '2026-06-15'],
            'week' => ['week', '2026-06-15', '2026-24'],
            'month' => ['month', '2026-06-15', '2026-06'],
            'semester H1' => ['semester', '2026-03-01', '2026-S1'],
            'semester H2' => ['semester', '2026-09-01', '2026-S2'],
        ];
    }

    public function testInvalidGranularityThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->controller->getAggregatedSeries('2026-01-01', '2026-03-31', 'year');
    }

    public function testStartAfterEndThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->controller->getAggregatedSeries('2026-03-01', '2026-02-01', 'month');
    }

    public function testInvalidDateFormatThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->controller->getAggregatedSeries('2026/02/01', '2026-03-31', 'month');
    }

    public function testExpensesByCategoryGroupsAndFilters(): void
    {
        $this->pdo->exec("INSERT INTO categories (name, type, color) VALUES ('Lazer', 'Variável', '#FF5500')");
        $lazerId = (int) $this->pdo->lastInsertId();
        $geralId = (int) $this->pdo->query('SELECT id FROM categories WHERE name = "Geral"')->fetchColumn();

        $this->pdo->exec(
            "INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES ({$lazerId}, 'saída', '2026-02-15', 'Test', 'op', 300.00, 'Cinema', '2026-02')"
        );

        $result = $this->controller->getExpensesByCategory('2026-02-01', '2026-03-31');

        $this->assertArrayHasKey('categories', $result);
        $this->assertGreaterThanOrEqual(2, count($result['categories']));

        $byName = [];
        foreach ($result['categories'] as $row) {
            $byName[$row['name']] = $row;
        }

        $this->assertArrayHasKey('Lazer', $byName);
        $this->assertEqualsWithDelta(300.00, $byName['Lazer']['amount'], 0.01);
        $this->assertSame('#FF5500', $byName['Lazer']['color']);

        $this->assertArrayHasKey('Geral', $byName);
        $this->assertEqualsWithDelta(350.00, $byName['Geral']['amount'], 0.01);
    }

    public function testFixedVsVariableSeriesByMonth(): void
    {
        $this->pdo->exec("INSERT INTO categories (name, type, color) VALUES ('Assinaturas', 'Fixo', '#253762')");
        $fixoId = (int) $this->pdo->lastInsertId();
        $geralId = (int) $this->pdo->query('SELECT id FROM categories WHERE name = "Geral"')->fetchColumn();

        $this->pdo->exec(
            "INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES ({$fixoId}, 'saída', '2026-03-05', 'Test', 'op', 80.00, 'Netflix', '2026-03')"
        );

        $result = $this->controller->getFixedVsVariableSeries('2026-02-01', '2026-03-31', 'month');

        $this->assertCount(2, $result['series']);
        $mar = $result['series'][1];
        $this->assertSame('2026-03', $mar['period_label']);
        $this->assertEqualsWithDelta(80.00, $mar['fixed'], 0.01);
        $this->assertEqualsWithDelta(150.00, $mar['variable'], 0.01);
    }

    public function testCategoryEvolutionSeriesForSingleCategory(): void
    {
        $this->pdo->exec("INSERT INTO categories (name, type, color) VALUES ('Lazer', 'Variável', '#A09CD9')");
        $lazerId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec(
            "INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES ({$lazerId}, 'saída', '2026-02-10', 'Test', 'op', 50.00, 'Cinema', '2026-02')"
        );
        $this->pdo->exec(
            "INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES ({$lazerId}, 'saída', '2026-03-05', 'Test', 'op', 75.00, 'Show', '2026-03')"
        );

        $result = $this->controller->getCategoryEvolutionSeries(
            '2026-02-01',
            '2026-03-31',
            'month',
            $lazerId,
        );

        $this->assertSame('Lazer', $result['category_name']);
        $this->assertCount(2, $result['series']);
        $this->assertEqualsWithDelta(50.00, $result['series'][0]['amount'], 0.01);
        $this->assertEqualsWithDelta(75.00, $result['series'][1]['amount'], 0.01);
    }

    public function testYieldGrowthSeriesMonthlyAndCumulative(): void
    {
        $catId = (int) $this->pdo->query('SELECT id FROM categories LIMIT 1')->fetchColumn();

        $this->pdo->exec(
            "INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES ({$catId}, 'rendimento', '2026-04-10', 'Test', 'op', 100.00, 'CDI', '2026-04')"
        );
        $this->pdo->exec(
            "INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES ({$catId}, 'rendimento', '2026-05-20', 'Test', 'op', 50.00, 'CDI', '2026-05')"
        );

        $result = $this->controller->getYieldGrowthSeries('2026-04-01', '2026-05-31', 'month');

        $this->assertSame(['2026-04', '2026-05'], $result['labels']);
        $this->assertEqualsWithDelta([100.00, 50.00], $result['rendimento_mensal'], 0.01);
        $this->assertEqualsWithDelta([100.00, 150.00], $result['rendimento_acumulado'], 0.01);
    }

    public function testYieldGrowthSeriesFillsEmptyMonthsWithZero(): void
    {
        $catId = (int) $this->pdo->query('SELECT id FROM categories LIMIT 1')->fetchColumn();

        $this->pdo->exec(
            "INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES ({$catId}, 'rendimento', '2026-07-15', 'Test', 'op', 25.00, 'CDI', '2026-07')"
        );
        $this->pdo->exec(
            "INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES ({$catId}, 'rendimento', '2026-09-10', 'Test', 'op', 75.00, 'CDI', '2026-09')"
        );

        $result = $this->controller->getYieldGrowthSeries('2026-07-01', '2026-09-30', 'month');

        $this->assertSame(['2026-07', '2026-08', '2026-09'], $result['labels']);
        $this->assertEqualsWithDelta([25.00, 0.00, 75.00], $result['rendimento_mensal'], 0.01);
        $this->assertEqualsWithDelta([25.00, 25.00, 100.00], $result['rendimento_acumulado'], 0.01);
    }

    public function testExpensesByCategoryExcludesIncomeAndOutOfRange(): void
    {
        $catId = (int) $this->pdo->query('SELECT id FROM categories LIMIT 1')->fetchColumn();
        $this->pdo->exec(
            "INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES ({$catId}, 'entrada', '2026-02-10', 'Test', 'op', 999.00, 'Salario', '2026-02')"
        );
        $this->pdo->exec(
            "INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description, month_year)
             VALUES ({$catId}, 'saída', '2025-12-01', 'Test', 'op', 500.00, 'Antiga', '2025-12')"
        );

        $result = $this->controller->getExpensesByCategory('2026-02-01', '2026-02-28');
        $total  = array_sum(array_column($result['categories'], 'amount'));

        $this->assertEqualsWithDelta(200.00, $total, 0.01);
    }
}

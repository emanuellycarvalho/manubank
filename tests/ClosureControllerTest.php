<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para ClosureController.
 *
 * Usa PDO :memory: com schema mínimo para isolar de finance.sqlite.
 */
final class ClosureControllerTest extends TestCase
{
    private \PDO $pdo;
    private \ReimbursementController $reimbursements;
    private \ClosureController $controller;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->buildSchema();
        $this->seedData();

        $this->reimbursements = new \ReimbursementController($this->pdo);
        $this->controller     = new \ClosureController($this->pdo, $this->reimbursements);
    }

    // ---------------------------------------------------------------------------
    // getMonthlySummary
    // ---------------------------------------------------------------------------

    public function testGetMonthlySummaryReturnsCorrectStructure(): void
    {
        $summary = $this->controller->getMonthlySummary('2026-04');

        $this->assertArrayHasKey('month_year', $summary);
        $this->assertArrayHasKey('rollover', $summary);
        $this->assertArrayHasKey('total_income', $summary);
        $this->assertArrayHasKey('total_effective_expenses', $summary);
        $this->assertArrayHasKey('available_cash', $summary);
        $this->assertArrayHasKey('effective_expenses_by_category', $summary);
        $this->assertSame('2026-04', $summary['month_year']);
    }

    public function testGetMonthlySummaryUsesRolloverOverride(): void
    {
        $summary = $this->controller->getMonthlySummary('2026-04', 999.99);

        $this->assertEqualsWithDelta(999.99, $summary['rollover'], 0.001);
    }

    public function testGetMonthlySummaryCalculatesAvailableCash(): void
    {
        // Receita: 1500.00 | Despesa: 45.90 (seed) | Rollover override: 100.00
        // Caixa = 100 + 1500 - 45.90 = 1554.10
        $summary = $this->controller->getMonthlySummary('2026-04', 100.00);

        $this->assertEqualsWithDelta(1554.10, $summary['available_cash'], 0.01);
    }

    public function testGetMonthlySummaryCalculatesRolloverFromPreviousMonths(): void
    {
        // MercadoPago em 2026-03: +300 entrada, -100 saída = +200 rollover
        $this->insertTransaction('MercadoPago', 'entrada', 300.00, '2026-03');
        $this->insertTransaction('MercadoPago', 'saída',   100.00, '2026-03');

        $summary = $this->controller->getMonthlySummary('2026-04');

        $this->assertEqualsWithDelta(200.00, $summary['rollover'], 0.001);
    }

    public function testGetMonthlySummaryRolloverIgnoresCurrentMonth(): void
    {
        // Transações do próprio 2026-04 não devem entrar no rollover
        $this->insertTransaction('MercadoPago', 'entrada', 500.00, '2026-04');

        $summary = $this->controller->getMonthlySummary('2026-04');

        // Rollover continua 0 (nenhuma transação MP anterior a 2026-04)
        $this->assertEqualsWithDelta(0.0, $summary['rollover'], 0.001);
    }

    // ---------------------------------------------------------------------------
    // saveClosure — pattern_broken
    // ---------------------------------------------------------------------------

    /**
     * @return array<string, array{float, float, int}>
     * Parâmetros: available_cash para atingir o actual_balance, soma de aportes, pattern_broken esperado
     */
    public static function patternBrokenProvider(): array
    {
        return [
            // actual_balance = available_cash - totalInvested
            // pattern ok: actual_balance >= 1200 E totalInvested >= 4000
            'balance_ok_invest_ok'    => [6200.0, 4000.0, 0],
            // actual_balance = 6200 - 5300 = 900 < 1200 → quebrado
            'balance_below_threshold' => [6200.0, 5300.0, 1],
            // actual_balance = 6200 - 5000 = 1200, mas totalInvested 3500 < 4000 → quebrado
            'invest_below_threshold'  => [5700.0, 3500.0, 1],
            // ambos abaixo
            'both_below'              => [5000.0, 3500.0, 1],
        ];
    }

    #[DataProvider('patternBrokenProvider')]
    public function testSaveClosurePatternBroken(
        float $availableCash,
        float $totalInvested,
        int $expectedPatternBroken
    ): void {
        // Usar rolloverOverride para controlar available_cash
        // available_cash = rollover + income - expenses
        // income = 1500, expenses = 45.90, logo rollover = availableCash - 1500 + 45.90
        $rollover = $availableCash - 1500.00 + 45.90;

        $investments = [['objective' => 'Imóvel', 'target_amount' => $totalInvested, 'actual_amount' => $totalInvested, 'is_extra_surplus' => false]];

        $closureId = $this->controller->saveClosure('2026-04', $investments, $rollover);

        $row = $this->pdo->query("SELECT pattern_broken FROM monthly_closures WHERE id = {$closureId}")->fetch();
        $this->assertSame($expectedPatternBroken, (int) $row['pattern_broken']);
    }

    // ---------------------------------------------------------------------------
    // saveClosure — surplus_invested
    // ---------------------------------------------------------------------------

    public function testSaveClosureSurplusInvestedWhenBalanceAboveTarget(): void
    {
        // actual_balance = available_cash - invested = (100 + 1500 - 45.90) - 0 = 1554.10
        // surplus = 1554.10 - 1200 = 354.10
        $closureId = $this->controller->saveClosure('2026-04', [], 100.00);

        $row = $this->pdo->query("SELECT surplus_invested, actual_balance FROM monthly_closures WHERE id = {$closureId}")->fetch();

        $this->assertEqualsWithDelta(354.10, (float) $row['surplus_invested'], 0.01);
    }

    public function testSaveClosureSurplusIsZeroWhenBalanceBelowTarget(): void
    {
        // Rollover negativo para forçar actual_balance < 1200
        $closureId = $this->controller->saveClosure('2026-04', [], -500.00);

        $row = $this->pdo->query("SELECT surplus_invested FROM monthly_closures WHERE id = {$closureId}")->fetch();

        $this->assertEqualsWithDelta(0.0, (float) $row['surplus_invested'], 0.001);
    }

    // ---------------------------------------------------------------------------
    // saveClosure — idempotência
    // ---------------------------------------------------------------------------

    public function testSaveClosureIsIdempotent(): void
    {
        $investments = [['objective' => 'Teste', 'target_amount' => 500.0, 'actual_amount' => 500.0, 'is_extra_surplus' => false]];

        $this->controller->saveClosure('2026-04', $investments, 0.0);
        $this->controller->saveClosure('2026-04', $investments, 0.0); // Re-fechar

        $count = $this->pdo->query("SELECT COUNT(*) FROM monthly_closures WHERE month_year = '2026-04'")->fetchColumn();
        $this->assertSame('1', (string) $count);
    }

    public function testSaveClosureReplacesOldAllocations(): void
    {
        $inv1 = [['objective' => 'A', 'target_amount' => 100.0, 'actual_amount' => 100.0, 'is_extra_surplus' => false]];
        $inv2 = [
            ['objective' => 'B', 'target_amount' => 200.0, 'actual_amount' => 200.0, 'is_extra_surplus' => false],
            ['objective' => 'C', 'target_amount' => 300.0, 'actual_amount' => 300.0, 'is_extra_surplus' => false],
        ];

        $this->controller->saveClosure('2026-04', $inv1, 0.0);
        $closureId = $this->controller->saveClosure('2026-04', $inv2, 0.0);

        $count = $this->pdo->query(
            "SELECT COUNT(*) FROM investment_allocations WHERE monthly_closure_id = {$closureId}"
        )->fetchColumn();

        $this->assertSame('2', (string) $count);
    }

    // ---------------------------------------------------------------------------
    // getClosure
    // ---------------------------------------------------------------------------

    public function testGetClosureReturnsNullWhenNotExists(): void
    {
        $result = $this->controller->getClosure('2099-12');

        $this->assertNull($result);
    }

    public function testGetClosureReturnsDataWithAllocations(): void
    {
        $investments = [
            ['objective' => 'Imóvel', 'target_amount' => 1000.0, 'actual_amount' => 1000.0, 'is_extra_surplus' => false],
        ];

        $this->controller->saveClosure('2026-04', $investments, 100.0);

        $closure = $this->controller->getClosure('2026-04');

        $this->assertNotNull($closure);
        $this->assertSame('2026-04', $closure['month_year']);
        $this->assertCount(1, $closure['allocations']);
        $this->assertSame('Imóvel', $closure['allocations'][0]['objective']);
    }

    public function testGetClosureReturnsEmptyAllocationsArray(): void
    {
        $this->controller->saveClosure('2026-04', [], 100.0);

        $closure = $this->controller->getClosure('2026-04');

        $this->assertNotNull($closure);
        $this->assertSame([], $closure['allocations']);
    }

    // ---------------------------------------------------------------------------
    // Helpers de setup
    // ---------------------------------------------------------------------------

    private function buildSchema(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL, type TEXT NOT NULL, color TEXT NOT NULL, is_active INTEGER DEFAULT 1
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL, type TEXT NOT NULL, date TEXT NOT NULL,
                origin TEXT NOT NULL, operation TEXT NOT NULL, amount DECIMAL(10,2) NOT NULL,
                raw_description TEXT NOT NULL, translated_description TEXT,
                installment_current INTEGER, installment_total INTEGER, month_year TEXT NOT NULL
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE reimbursement_claims (
                id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_id INTEGER NOT NULL,
                expected_amount DECIMAL(10,2) NOT NULL, description TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'Aberto'
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE reimbursement_payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT, claim_id INTEGER NOT NULL,
                income_transaction_id INTEGER NOT NULL, paid_amount DECIMAL(10,2) NOT NULL
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE monthly_closures (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                month_year TEXT NOT NULL UNIQUE,
                total_income DECIMAL(10,2) NOT NULL DEFAULT 0,
                target_balance DECIMAL(10,2) NOT NULL DEFAULT 1200.00,
                actual_balance DECIMAL(10,2) NOT NULL DEFAULT 0,
                surplus_invested DECIMAL(10,2) NOT NULL DEFAULT 0,
                pattern_broken INTEGER NOT NULL DEFAULT 0,
                notes TEXT, ai_insights TEXT
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE investment_allocations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                monthly_closure_id INTEGER NOT NULL,
                objective TEXT NOT NULL,
                target_amount DECIMAL(10,2) NOT NULL,
                actual_amount DECIMAL(10,2) NOT NULL,
                is_extra_surplus INTEGER NOT NULL DEFAULT 0
            )
        SQL);
    }

    private function seedData(): void
    {
        $this->pdo->exec("INSERT INTO categories (name, type, color) VALUES ('Transporte', 'Variável', '#6B8D9E')");
        $catId = (int) $this->pdo->lastInsertId();

        // Despesa de saída em 2026-04
        $this->pdo->exec("INSERT INTO transactions
            (category_id, type, date, origin, operation, amount, raw_description, month_year)
            VALUES ({$catId}, 'saída', '2026-04-15', 'Nubank', 'Credito', 45.90, 'Uber', '2026-04')");

        // Receita em 2026-04
        $this->pdo->exec("INSERT INTO transactions
            (category_id, type, date, origin, operation, amount, raw_description, month_year)
            VALUES ({$catId}, 'entrada', '2026-04-01', 'MercadoPago', 'Pix', 1500.00, 'Salario', '2026-04')");
    }

    /** Insere uma transação de teste com origem e tipo customizados. */
    private function insertTransaction(string $origin, string $type, float $amount, string $monthYear): void
    {
        $catId = $this->pdo->query('SELECT id FROM categories LIMIT 1')->fetchColumn();
        $this->pdo->exec("INSERT INTO transactions
            (category_id, type, date, origin, operation, amount, raw_description, month_year)
            VALUES ({$catId}, '{$type}', '{$monthYear}-01', '{$origin}', 'op', {$amount}, 'desc', '{$monthYear}')");
    }
}

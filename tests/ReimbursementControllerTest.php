<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para ReimbursementController.
 *
 * Usa PDO :memory: com schema mínimo para isolar de finance.sqlite.
 */
final class ReimbursementControllerTest extends TestCase
{
    private \PDO $pdo;
    private \ReimbursementController $controller;

    /** ID de uma transação de saída pré-inserida para os testes. */
    private int $expenseTransactionId;

    /** ID de uma transação de entrada pré-inserida para os testes. */
    private int $incomeTransactionId;

    private int $reimbursementCategoryId;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->buildSchema();
        $this->seedData();

        $this->controller = new \ReimbursementController($this->pdo);
    }

    // ---------------------------------------------------------------------------
    // createClaim
    // ---------------------------------------------------------------------------

    public function testCreateClaimReturnsNewId(): void
    {
        $id = $this->controller->createClaim($this->expenseTransactionId, 50.00, 'Rateio jantar');

        $this->assertGreaterThan(0, $id);
    }

    public function testCreateClaimInsertsWithStatusAberto(): void
    {
        $id = $this->controller->createClaim($this->expenseTransactionId, 50.00, 'Teste');

        $row = $this->pdo->query("SELECT status FROM reimbursement_claims WHERE id = {$id}")->fetch();
        $this->assertSame('Aberto', $row['status']);
    }

    public function testCreateClaimThrowsForInvalidTransaction(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->controller->createClaim(99999, 50.00, 'Teste');
    }

    public function testCreateClaimThrowsForZeroAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->controller->createClaim($this->expenseTransactionId, 0.0, 'Teste');
    }

    public function testCreateClaimThrowsForNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->controller->createClaim($this->expenseTransactionId, -10.0, 'Teste');
    }

    public function testCreateClaimAssignsReimbursementCategory(): void
    {
        $this->controller->createClaim($this->expenseTransactionId, 50.00, 'Rateio jantar');

        $catId = (int) $this->pdo->query(
            "SELECT category_id FROM transactions WHERE id = {$this->expenseTransactionId}"
        )->fetchColumn();

        $this->assertSame($this->reimbursementCategoryId, $catId);
    }

    // ---------------------------------------------------------------------------
    // registerPayment
    // ---------------------------------------------------------------------------

    public function testRegisterPaymentSetsStatusParcial(): void
    {
        $claimId = $this->controller->createClaim($this->expenseTransactionId, 100.00, 'Rateio');

        $this->controller->registerPayment($this->incomeTransactionId, [
            ['claim_id' => $claimId, 'paid_amount' => 60.00],
        ]);

        $row = $this->pdo->query("SELECT status FROM reimbursement_claims WHERE id = {$claimId}")->fetch();
        $this->assertSame('Parcial', $row['status']);
    }

    public function testRegisterPaymentSetsStatusQuitadoOnFullPayment(): void
    {
        $claimId = $this->controller->createClaim($this->expenseTransactionId, 100.00, 'Rateio');

        $this->controller->registerPayment($this->incomeTransactionId, [
            ['claim_id' => $claimId, 'paid_amount' => 100.00],
        ]);

        $row = $this->pdo->query("SELECT status FROM reimbursement_claims WHERE id = {$claimId}")->fetch();
        $this->assertSame('Quitado', $row['status']);
    }

    public function testRegisterPaymentAccumulatesAndPromotesToQuitado(): void
    {
        $claimId = $this->controller->createClaim($this->expenseTransactionId, 100.00, 'Rateio');

        // Primeiro pagamento parcial
        $this->controller->registerPayment($this->incomeTransactionId, [
            ['claim_id' => $claimId, 'paid_amount' => 60.00],
        ]);

        // Segundo pagamento que quita
        $this->controller->registerPayment($this->incomeTransactionId, [
            ['claim_id' => $claimId, 'paid_amount' => 40.00],
        ]);

        $row = $this->pdo->query("SELECT status FROM reimbursement_claims WHERE id = {$claimId}")->fetch();
        $this->assertSame('Quitado', $row['status']);
    }

    public function testRegisterPaymentWithMultipleAllocations(): void
    {
        $claim1 = $this->controller->createClaim($this->expenseTransactionId, 50.00, 'C1');
        $claim2 = $this->controller->createClaim($this->expenseTransactionId, 50.00, 'C2');

        $this->controller->registerPayment($this->incomeTransactionId, [
            ['claim_id' => $claim1, 'paid_amount' => 50.00],
            ['claim_id' => $claim2, 'paid_amount' => 25.00],
        ]);

        $row1 = $this->pdo->query("SELECT status FROM reimbursement_claims WHERE id = {$claim1}")->fetch();
        $row2 = $this->pdo->query("SELECT status FROM reimbursement_claims WHERE id = {$claim2}")->fetch();

        $this->assertSame('Quitado', $row1['status']);
        $this->assertSame('Parcial', $row2['status']);
    }

    public function testRegisterPaymentThrowsForUnknownClaim(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->controller->registerPayment($this->incomeTransactionId, [
            ['claim_id' => 99999, 'paid_amount' => 10.00],
        ]);
    }

    public function testRegisterPaymentAssignsReimbursementCategoryToIncome(): void
    {
        $claimId = $this->controller->createClaim($this->expenseTransactionId, 50.00, 'Rateio');

        $this->controller->registerPayment($this->incomeTransactionId, [
            ['claim_id' => $claimId, 'paid_amount' => 50.00],
        ]);

        $catId = (int) $this->pdo->query(
            "SELECT category_id FROM transactions WHERE id = {$this->incomeTransactionId}"
        )->fetchColumn();

        $this->assertSame($this->reimbursementCategoryId, $catId);
    }

    // ---------------------------------------------------------------------------
    // getEffectiveExpenses
    // ---------------------------------------------------------------------------

    public function testGetEffectiveExpensesWithoutClaimReturnsFullAmount(): void
    {
        $expenses = $this->controller->getEffectiveExpenses('2026-04');

        $this->assertNotEmpty($expenses);
        $first = $expenses[0];
        $this->assertArrayHasKey('effective_amount', $first);
        // Sem claim: effective_amount = amount completo da transação
        $this->assertEqualsWithDelta(45.90, $first['effective_amount'], 0.001);
    }

    public function testGetEffectiveExpensesDiscountsClaimAmount(): void
    {
        $this->controller->createClaim($this->expenseTransactionId, 20.00, 'Rateio');

        $expenses = $this->controller->getEffectiveExpenses('2026-04');

        $first = $expenses[0];
        // 45.90 - 20.00 = 25.90
        $this->assertEqualsWithDelta(25.90, $first['effective_amount'], 0.001);
    }

    public function testGetEffectiveExpensesReturnsEmptyForMonthWithNoExpenses(): void
    {
        $expenses = $this->controller->getEffectiveExpenses('2099-01');

        $this->assertSame([], $expenses);
    }

    // ---------------------------------------------------------------------------
    // getActiveClaims
    // ---------------------------------------------------------------------------

    public function testGetActiveClaimsReturnsOpenClaims(): void
    {
        $this->controller->createClaim($this->expenseTransactionId, 50.00, 'Pendente A');
        $this->controller->createClaim($this->expenseTransactionId, 30.00, 'Pendente B');

        $claims = $this->controller->getActiveClaims();

        $this->assertCount(2, $claims);
        $this->assertSame('Aberto', $claims[0]['status']);
    }

    public function testGetActiveClaimsExcludesQuitado(): void
    {
        $claimId = $this->controller->createClaim($this->expenseTransactionId, 50.00, 'A pagar');

        $this->controller->registerPayment($this->incomeTransactionId, [
            ['claim_id' => $claimId, 'paid_amount' => 50.00],
        ]);

        $claims = $this->controller->getActiveClaims();

        // Claim quitado não deve aparecer
        $ids = array_column($claims, 'id');
        $this->assertNotContains($claimId, $ids);
    }

    public function testGetActiveClaimsReturnsOutstandingAfterPartialPayment(): void
    {
        $claimId = $this->controller->createClaim($this->expenseTransactionId, 100.00, 'Rateio');

        $this->controller->registerPayment($this->incomeTransactionId, [
            ['claim_id' => $claimId, 'paid_amount' => 35.00],
        ]);

        $claims = $this->controller->getActiveClaims();
        $claim  = array_values(array_filter($claims, fn(array $c): bool => $c['id'] === $claimId))[0];

        $this->assertSame('Parcial', $claim['status']);
        $this->assertEqualsWithDelta(100.00, $claim['expected_amount'], 0.001);
        $this->assertEqualsWithDelta(35.00, $claim['paid_amount'], 0.001);
        $this->assertEqualsWithDelta(65.00, $claim['outstanding_amount'], 0.001);
    }

    // ---------------------------------------------------------------------------
    // Helpers de setup
    // ---------------------------------------------------------------------------

    private function buildSchema(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                color TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE transactions (
                id                     INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id            INTEGER NOT NULL,
                type                   TEXT    NOT NULL,
                date                   TEXT    NOT NULL,
                origin                 TEXT    NOT NULL,
                operation              TEXT    NOT NULL,
                amount                 DECIMAL(10,2) NOT NULL,
                raw_description        TEXT    NOT NULL,
                translated_description TEXT,
                installment_current    INTEGER,
                installment_total      INTEGER,
                month_year             TEXT    NOT NULL
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE reimbursement_claims (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                transaction_id  INTEGER NOT NULL,
                expected_amount DECIMAL(10,2) NOT NULL,
                description     TEXT    NOT NULL,
                status          TEXT    NOT NULL DEFAULT 'Aberto'
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE reimbursement_payments (
                id                    INTEGER PRIMARY KEY AUTOINCREMENT,
                claim_id              INTEGER NOT NULL,
                income_transaction_id INTEGER NOT NULL,
                paid_amount           DECIMAL(10,2) NOT NULL
            )
        SQL);
    }

    private function seedData(): void
    {
        $this->pdo->exec("INSERT INTO categories (name, type, color) VALUES ('Transporte', 'Variável', '#6B8D9E')");
        $catId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO categories (name, type, color) VALUES ('Reembolso/Terceiros', 'Neutro', '#7CB0A5')");
        $this->reimbursementCategoryId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO transactions
            (category_id, type, date, origin, operation, amount, raw_description, month_year)
            VALUES ({$catId}, 'saída', '2026-04-15', 'Nubank', 'Credito', 45.90, 'Uber', '2026-04')");
        $this->expenseTransactionId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO transactions
            (category_id, type, date, origin, operation, amount, raw_description, month_year)
            VALUES ({$catId}, 'entrada', '2026-04-01', 'MercadoPago', 'Pix', 500.00, 'Salario', '2026-04')");
        $this->incomeTransactionId = (int) $this->pdo->lastInsertId();
    }
}

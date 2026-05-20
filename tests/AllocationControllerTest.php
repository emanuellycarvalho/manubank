<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class AllocationControllerTest extends TestCase
{
    private \PDO $pdo;
    private \AllocationController $controller;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE investment_objectives (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                target_amount REAL NOT NULL,
                end_date TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE investment_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                objective_id INTEGER NOT NULL,
                type TEXT NOT NULL CHECK (type IN ('entrada', 'saída')),
                amount REAL NOT NULL,
                date TEXT NOT NULL,
                description TEXT,
                FOREIGN KEY (objective_id) REFERENCES investment_objectives (id)
                    ON DELETE CASCADE
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE investment_allocations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                objective_id INTEGER,
                bank TEXT NOT NULL,
                type TEXT,
                liquidity TEXT,
                amount REAL NOT NULL,
                priority INTEGER,
                cdi_percentage REAL,
                monthly_rate REAL,
                yearly_rate REAL,
                description TEXT,
                FOREIGN KEY (objective_id) REFERENCES investment_objectives (id)
                    ON DELETE SET NULL
            )
        SQL);

        $this->pdo->exec(
            "INSERT INTO investment_objectives (name, target_amount, end_date, created_at)
             VALUES ('Reserva', 30000, '2028-12-31', '2026-01-01')"
        );

        $this->controller = new \AllocationController($this->pdo);
    }

    public function testCreateListAndJoinObjectiveName(): void
    {
        $created = $this->controller->create([
            'objective_id'   => 1,
            'bank'           => 'Nubank',
            'type'           => 'CDI',
            'liquidity'      => 'Diária',
            'amount'         => 15000.50,
            'priority'       => 4,
            'cdi_percentage' => 100,
            'description'    => 'Conta principal',
        ]);

        $this->assertSame(1, $created['id']);
        $this->assertSame('Reserva', $created['objective_name']);
        $this->assertEqualsWithDelta(15000.50, $created['amount'], 0.01);

        $list = $this->controller->listAll();
        $this->assertCount(1, $list);
        $this->assertSame('Nubank', $list[0]['bank']);
    }

    public function testUpdateAndDelete(): void
    {
        $row = $this->controller->create([
            'bank'   => 'XP',
            'amount' => 5000,
        ]);

        $updated = $this->controller->update($row['id'], [
            'bank'     => 'XP Investimentos',
            'amount'   => 6000,
            'priority' => 5,
        ]);

        $this->assertSame('XP Investimentos', $updated['bank']);
        $this->assertSame(5, $updated['priority']);

        $this->controller->delete($row['id']);
        $this->assertCount(0, $this->controller->listAll());
    }

    public function testRejectsInvalidPriority(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->controller->create([
            'bank'     => 'Banco',
            'amount'   => 100,
            'priority' => 6,
        ]);
    }

    public function testCreateWithObjectiveRegistersInitialEntry(): void
    {
        $this->controller->create([
            'objective_id' => 1,
            'bank'         => 'Picpay',
            'amount'       => 3000,
        ]);

        $entries = $this->pdo->query('SELECT * FROM investment_entries')->fetchAll();
        $this->assertCount(1, $entries);
        $this->assertSame('entrada', $entries[0]['type']);
        $this->assertEqualsWithDelta(3000.0, (float) $entries[0]['amount'], 0.01);
        $this->assertStringContainsString('Saldo inicial consolidado', (string) $entries[0]['description']);
    }

    public function testUpdateAmountCreatesDeltaEntryInObjective(): void
    {
        $row = $this->controller->create([
            'objective_id' => 1,
            'bank'         => 'BMG',
            'amount'       => 1000,
        ]);

        $this->controller->update($row['id'], [
            'objective_id' => 1,
            'bank'         => 'BMG',
            'amount'       => 1500,
        ]);

        $entries = $this->pdo->query(
            'SELECT type, amount, description FROM investment_entries ORDER BY id ASC'
        )->fetchAll();

        $this->assertCount(2, $entries);
        $this->assertSame('entrada', $entries[1]['type']);
        $this->assertEqualsWithDelta(500.0, (float) $entries[1]['amount'], 0.01);
        $this->assertStringContainsString('Ajuste consolidado', (string) $entries[1]['description']);
    }

    public function testValorAcumuladoEqualsAllocationSum(): void
    {
        $this->controller->create([
            'objective_id' => 1,
            'bank'         => 'Nubank',
            'amount'       => 2000,
        ]);
        $this->controller->create([
            'objective_id' => 1,
            'bank'         => 'XP',
            'amount'       => 1000,
        ]);

        $investment = new \InvestmentController($this->pdo);
        $objectives = $investment->getObjectivesWithMetrics();

        $this->assertCount(1, $objectives);
        $this->assertEqualsWithDelta(3000.0, $objectives[0]['valor_acumulado'], 0.01);
    }
}

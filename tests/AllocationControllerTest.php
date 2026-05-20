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
}

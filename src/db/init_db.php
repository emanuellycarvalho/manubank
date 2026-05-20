<?php

declare(strict_types=1);

/**
 * Script CLI de inicialização da base de dados SQLite.
 *
 * Uso: php src/db/init_db.php
 */

require_once __DIR__ . '/Database.php';

/**
 * Executa uma query preparada e reporta erros de forma legível.
 *
 * @param array<int, mixed> $params
 */
function executePrepared(PDO $pdo, string $sql, array $params = []): void
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
}

/**
 * @return array<string, string>
 */
function getTableDefinitions(): array
{
    return [
        'categories' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS categories (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT    NOT NULL,
                type       TEXT    NOT NULL CHECK (type IN ('Fixo', 'Variável', 'Neutro')),
                color      TEXT    NOT NULL,
                is_active  INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1))
            )
            SQL,

        'transactions' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS transactions (
                id                     INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id            INTEGER NOT NULL,
                type                   TEXT    NOT NULL CHECK (type IN ('entrada', 'saída', 'rendimento')),
                date                   TEXT    NOT NULL,
                origin                 TEXT    NOT NULL,
                operation              TEXT    NOT NULL,
                amount                 DECIMAL(10, 2) NOT NULL,
                raw_description        TEXT    NOT NULL,
                translated_description TEXT,
                installment_current    INTEGER,
                installment_total      INTEGER,
                month_year             TEXT    NOT NULL,
                external_id            TEXT    UNIQUE NULL,
                FOREIGN KEY (category_id) REFERENCES categories (id)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL,

        'parsing_rules' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS parsing_rules (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id     INTEGER NOT NULL,
                substring       TEXT    NOT NULL,
                translated_name TEXT    NOT NULL,
                is_active       INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
                FOREIGN KEY (category_id) REFERENCES categories (id)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL,

        'monthly_closures' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS monthly_closures (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                month_year       TEXT            NOT NULL UNIQUE,
                total_income     DECIMAL(10, 2)  NOT NULL DEFAULT 0,
                target_balance   DECIMAL(10, 2)  NOT NULL DEFAULT 1200.00,
                actual_balance   DECIMAL(10, 2)  NOT NULL DEFAULT 0,
                surplus_invested DECIMAL(10, 2)  NOT NULL DEFAULT 0,
                pattern_broken   INTEGER         NOT NULL DEFAULT 0 CHECK (pattern_broken IN (0, 1)),
                notes            TEXT,
                ai_insights      TEXT
            )
            SQL,

        'closure_allocations' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS closure_allocations (
                id                 INTEGER PRIMARY KEY AUTOINCREMENT,
                monthly_closure_id INTEGER NOT NULL,
                objective          TEXT    NOT NULL,
                target_amount      DECIMAL(10, 2) NOT NULL,
                actual_amount      DECIMAL(10, 2) NOT NULL,
                is_extra_surplus   INTEGER NOT NULL DEFAULT 0 CHECK (is_extra_surplus IN (0, 1)),
                FOREIGN KEY (monthly_closure_id) REFERENCES monthly_closures (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL,

        'investment_allocations' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS investment_allocations (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                objective_id    INTEGER,
                bank            TEXT    NOT NULL,
                type            TEXT,
                liquidity       TEXT,
                amount          REAL    NOT NULL,
                priority        INTEGER CHECK (priority IS NULL OR (priority >= 1 AND priority <= 5)),
                cdi_percentage  REAL,
                monthly_rate    REAL,
                yearly_rate     REAL,
                description     TEXT,
                FOREIGN KEY (objective_id) REFERENCES investment_objectives (id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            )
            SQL,

        'reimbursement_claims' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS reimbursement_claims (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                transaction_id  INTEGER NOT NULL,
                expected_amount DECIMAL(10, 2) NOT NULL,
                description     TEXT    NOT NULL,
                status          TEXT    NOT NULL DEFAULT 'Aberto'
                    CHECK (status IN ('Aberto', 'Parcial', 'Quitado')),
                FOREIGN KEY (transaction_id) REFERENCES transactions (id)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL,

        'reimbursement_payments' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS reimbursement_payments (
                id                    INTEGER PRIMARY KEY AUTOINCREMENT,
                claim_id              INTEGER NOT NULL,
                income_transaction_id INTEGER NOT NULL,
                paid_amount           DECIMAL(10, 2) NOT NULL,
                FOREIGN KEY (claim_id) REFERENCES reimbursement_claims (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                FOREIGN KEY (income_transaction_id) REFERENCES transactions (id)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL,

        'investment_objectives' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS investment_objectives (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                name          TEXT NOT NULL,
                target_amount REAL NOT NULL,
                end_date      TEXT NOT NULL,
                created_at    TEXT NOT NULL
            )
            SQL,

        'investment_entries' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS investment_entries (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                objective_id INTEGER NOT NULL,
                type         TEXT NOT NULL CHECK (type IN ('entrada', 'saída')),
                amount       REAL NOT NULL,
                date         TEXT NOT NULL,
                description  TEXT,
                FOREIGN KEY (objective_id) REFERENCES investment_objectives (id)
                    ON DELETE CASCADE
            )
            SQL,
    ];
}

/**
 * Renomeia a tabela legada de alocações de fechamento (schema antigo).
 */
function migrateLegacyInvestmentAllocationsTable(PDO $pdo): void
{
    $exists = $pdo->query(
        "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'investment_allocations' LIMIT 1"
    )->fetchColumn();

    if ($exists === false) {
        return;
    }

    $columns = $pdo->query('PRAGMA table_info(investment_allocations)')->fetchAll(PDO::FETCH_ASSOC);
    $names   = array_column($columns, 'name');

    if (!in_array('monthly_closure_id', $names, true) || in_array('bank', $names, true)) {
        return;
    }

    $pdo->exec('ALTER TABLE investment_allocations RENAME TO closure_allocations');
    echo "Migração: investment_allocations (fechamento) → closure_allocations\n";
}

/**
 * Cria índices auxiliares para consultas frequentes.
 */
function createIndexes(PDO $pdo): void
{
    $indexes = [
        'CREATE INDEX IF NOT EXISTS idx_transactions_category_id ON transactions (category_id)',
        'CREATE INDEX IF NOT EXISTS idx_transactions_month_year ON transactions (month_year)',
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_uniq_transaction ON transactions (date, origin, operation, raw_description, amount)',
        'CREATE INDEX IF NOT EXISTS idx_parsing_rules_category_id ON parsing_rules (category_id)',
        'CREATE INDEX IF NOT EXISTS idx_closure_allocations_closure_id ON closure_allocations (monthly_closure_id)',
        'CREATE INDEX IF NOT EXISTS idx_investment_allocations_objective_id ON investment_allocations (objective_id)',
        'CREATE INDEX IF NOT EXISTS idx_reimbursement_claims_transaction_id ON reimbursement_claims (transaction_id)',
        'CREATE INDEX IF NOT EXISTS idx_reimbursement_payments_claim_id ON reimbursement_payments (claim_id)',
        'CREATE INDEX IF NOT EXISTS idx_investment_entries_objective_id ON investment_entries (objective_id)',
    ];

    foreach ($indexes as $sql) {
        executePrepared($pdo, $sql);
    }
}

// --- Execução CLI ---

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via linha de comandos.\n");
    exit(1);
}

try {
    $pdo = Database::getConnection();

    $pdo->exec('PRAGMA foreign_keys = ON;');

    $tables = getTableDefinitions();

    migrateLegacyInvestmentAllocationsTable($pdo);

    foreach ($tables as $tableName => $createSql) {
        executePrepared($pdo, $createSql);
        echo "Tabela criada/verificada: {$tableName}\n";
    }

    createIndexes($pdo);
    echo "Índices criados/verificados.\n";

    $dbPath = Database::getDatabasePath();
    echo "\nBase de dados inicializada com sucesso.\n";
    echo "Ficheiro: {$dbPath}\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Erro ao inicializar a base de dados: {$exception->getMessage()}\n");
    exit(1);
}

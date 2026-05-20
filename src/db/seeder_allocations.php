<?php

declare(strict_types=1);

/**
 * Seeder CLI para investment_allocations (consolidado de investimentos).
 *
 * Resolve objetivos pelo nome (cria se não existirem).
 *
 * Uso:
 *   php src/db/seeder_allocations.php           (aborta se já existirem alocações)
 *   php src/db/seeder_allocations.php --force   (limpa alocações e reinsere)
 */

require_once __DIR__ . '/Database.php';

/**
 * @var array<int, array{
 *   bank: string,
 *   type: string,
 *   cdi_percentage: float,
 *   monthly_pct: float,
 *   yearly_pct: float,
 *   liquidity: string,
 *   amount: float,
 *   objective_name: string,
 *   priority: int,
 *   description: string|null
 * }>
 */
const ALLOCATIONS = [
    [
        'bank'            => 'Mercado Pago',
        'type'            => 'CDI',
        'cdi_percentage'  => 115,
        'monthly_pct'     => 1.25,
        'yearly_pct'      => 16.12,
        'liquidity'       => 'Diária',
        'amount'          => 5000.00,
        'objective_name'  => 'Intercâmbio',
        'priority'        => 5,
        'description'     => 'Limite de 5k',
    ],
    [
        'bank'            => 'BMG',
        'type'            => 'CDI',
        'cdi_percentage'  => 110,
        'monthly_pct'     => 1.20,
        'yearly_pct'      => 15.38,
        'liquidity'       => 'Diária',
        'amount'          => 16818.01,
        'objective_name'  => 'Intercâmbio',
        'priority'        => 5,
        'description'     => 'Não tem como reinvestir',
    ],
    [
        'bank'            => 'BMG',
        'type'            => 'CDI',
        'cdi_percentage'  => 110,
        'monthly_pct'     => 1.20,
        'yearly_pct'      => 15.38,
        'liquidity'       => 'Diária',
        'amount'          => 8076.23,
        'objective_name'  => 'Imóvel',
        'priority'        => 5,
        'description'     => 'Não tem como reinvestir',
    ],
    [
        'bank'            => 'BMG',
        'type'            => 'Fundo Segurança',
        'cdi_percentage'  => 109,
        'monthly_pct'     => 1.19,
        'yearly_pct'      => 15.23,
        'liquidity'       => 'Diária',
        'amount'          => 8150.29,
        'objective_name'  => 'Imóvel',
        'priority'        => 5,
        'description'     => 'Não tem como reinvestir',
    ],
    [
        'bank'            => 'Picpay',
        'type'            => 'CDI',
        'cdi_percentage'  => 102,
        'monthly_pct'     => 1.11,
        'yearly_pct'      => 14.19,
        'liquidity'       => 'Diária',
        'amount'          => 3058.06,
        'objective_name'  => 'Reserva de emergência',
        'priority'        => 3,
        'description'     => null,
    ],
];

function allocationsExist(PDO $pdo): bool
{
    return (int) $pdo->query('SELECT COUNT(*) FROM investment_allocations')->fetchColumn() > 0;
}

function truncateAllocations(PDO $pdo): void
{
    $pdo->exec('DELETE FROM investment_allocations');
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'investment_allocations'");
}

function percentToRate(float $percent): float
{
    return round($percent / 100, 6);
}

function findObjectiveIdByName(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM investment_objectives WHERE TRIM(name) = TRIM(:name) COLLATE NOCASE LIMIT 1'
    );
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
}

function ensureObjective(PDO $pdo, string $name): int
{
    $existing = findObjectiveIdByName($pdo, $name);
    if ($existing !== null) {
        return $existing;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO investment_objectives (name, target_amount, end_date, created_at)
         VALUES (:name, :target_amount, :end_date, :created_at)'
    );
    $stmt->execute([
        ':name'          => $name,
        ':target_amount' => 0,
        ':end_date'      => '2030-12-31',
        ':created_at'    => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d'),
    ]);

    $id = (int) $pdo->lastInsertId();
    echo "Objetivo criado: {$name} (id {$id})\n";

    return $id;
}

/**
 * @param array<int, array<string, mixed>> $allocations
 */
function seedAllocations(PDO $pdo, array $allocations): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO investment_allocations (
            objective_id, bank, type, liquidity, amount, priority,
            cdi_percentage, monthly_rate, yearly_rate, description
         ) VALUES (
            :objective_id, :bank, :type, :liquidity, :amount, :priority,
            :cdi_percentage, :monthly_rate, :yearly_rate, :description
         )'
    );

    foreach ($allocations as $row) {
        $objectiveId = ensureObjective($pdo, $row['objective_name']);

        $stmt->execute([
            ':objective_id'   => $objectiveId,
            ':bank'           => $row['bank'],
            ':type'           => $row['type'],
            ':liquidity'      => $row['liquidity'],
            ':amount'         => round((float) $row['amount'], 2),
            ':priority'       => $row['priority'],
            ':cdi_percentage' => $row['cdi_percentage'],
            ':monthly_rate'   => percentToRate((float) $row['monthly_pct']),
            ':yearly_rate'    => percentToRate((float) $row['yearly_pct']),
            ':description'    => $row['description'],
        ]);

        echo "Alocação: {$row['bank']} → {$row['objective_name']} (R$ "
            . number_format((float) $row['amount'], 2, ',', '.') . ")\n";
    }
}

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via linha de comandos.\n");
    exit(1);
}

$isForce = in_array('--force', $argv ?? [], true);

try {
    $pdo = Database::getConnection();

    if (allocationsExist($pdo)) {
        if (!$isForce) {
            echo "A tabela investment_allocations já contém registos. Use --force para recriar.\n";
            exit(0);
        }

        echo "Modo --force: a limpar investment_allocations...\n";
        $pdo->beginTransaction();
        truncateAllocations($pdo);
        $pdo->commit();
        echo "Tabela limpa.\n\n";
    }

    $pdo->beginTransaction();
    seedAllocations($pdo, ALLOCATIONS);
    $pdo->commit();

    echo "\n" . count(ALLOCATIONS) . " alocações inseridas com sucesso.\n";
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Erro: {$exception->getMessage()}\n");
    exit(1);
}

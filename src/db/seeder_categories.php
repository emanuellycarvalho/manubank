<?php

declare(strict_types=1);

/**
 * Seeder CLI para a tabela categories.
 *
 * Uso:
 *   php src/db/seeder_categories.php           (aborta se já existirem categorias)
 *   php src/db/seeder_categories.php --force   (limpa parsing_rules + categories e reinsere)
 */

require_once __DIR__ . '/Database.php';

// ---------------------------------------------------------------------------
// Dados de seed
// ---------------------------------------------------------------------------

/**
 * Categorias do sistema de gestão financeira.
 *
 * @var array<int, array{name: string, type: string, color: string}>
 */
const CATEGORIES = [
    ['name' => 'Transporte',          'type' => 'Variável', 'color' => '#6B8D9E'],
    ['name' => 'Comer Fora',          'type' => 'Variável', 'color' => '#D99B6C'],
    ['name' => 'Assinatura',          'type' => 'Fixo',     'color' => '#7E8A9E'],
    ['name' => 'Saúde',               'type' => 'Fixo',     'color' => '#6C9E7A'],
    ['name' => 'Supermercado',        'type' => 'Variável', 'color' => '#8A9E6B'],
    ['name' => 'Docinho',             'type' => 'Variável', 'color' => '#D49C9E'],
    ['name' => 'Lazer',               'type' => 'Variável', 'color' => '#A09CD9'],
    ['name' => 'Estética',            'type' => 'Variável', 'color' => '#D9B09C'],
    ['name' => 'Lanche',              'type' => 'Variável', 'color' => '#D9A06C'],
    ['name' => 'Variedades',          'type' => 'Variável', 'color' => '#B8B8B8'],
    ['name' => 'Reembolso/Terceiros', 'type' => 'Neutro',   'color' => '#7CB0A5'],
    ['name' => 'Movimentação interna', 'type' => 'Neutro',   'color' => '#8A8F9E'],
    ['name' => 'Outros',              'type' => 'Variável', 'color' => '#9C9C9C'],
    ['name' => 'Atlética',            'type' => 'Variável', 'color' => '#5C8F9E'],
    ['name' => 'Casa',                'type' => 'Fixo',     'color' => '#A89A7A'],
    ['name' => 'Cuidados',            'type' => 'Variável', 'color' => '#9BC4B0'],
    ['name' => 'Presente',            'type' => 'Variável', 'color' => '#D9A0A0'],
    ['name' => 'Vestuário',           'type' => 'Variável', 'color' => '#A6A6C0'],
];

// ---------------------------------------------------------------------------
// Funções auxiliares
// ---------------------------------------------------------------------------

/**
 * Verifica se a tabela categories já tem registos.
 */
function categoriesExist(PDO $pdo): bool
{
    $stmt = $pdo->query('SELECT COUNT(*) FROM categories');

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Remove todas as parsing_rules e categories dentro de uma transação.
 * A ordem respeita a FK: primeiro parsing_rules (que referencia categories).
 */
function truncateSeedTables(PDO $pdo): void
{
    $pdo->exec('DELETE FROM parsing_rules');
    $pdo->exec('DELETE FROM categories');
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('categories', 'parsing_rules')");
}

/**
 * Insere as categorias usando um prepared statement reutilizado.
 *
 * @param array<int, array{name: string, type: string, color: string}> $categories
 */
function seedCategories(PDO $pdo, array $categories): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO categories (name, type, color, is_active) VALUES (:name, :type, :color, :is_active)'
    );

    foreach ($categories as $category) {
        $stmt->execute([
            ':name'      => $category['name'],
            ':type'      => $category['type'],
            ':color'     => $category['color'],
            ':is_active' => 1,
        ]);
        echo "Inserida categoria: {$category['name']} ({$category['type']}, {$category['color']})\n";
    }
}

// ---------------------------------------------------------------------------
// Execução CLI
// ---------------------------------------------------------------------------

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via linha de comandos.\n");
    exit(1);
}

$isForce = in_array('--force', $argv ?? [], true);

try {
    $pdo = Database::getConnection();

    if (categoriesExist($pdo)) {
        if (!$isForce) {
            echo "A tabela categories já contém registos. Use --force para recriar.\n";
            exit(0);
        }

        echo "Modo --force: a limpar parsing_rules e categories...\n";
        $pdo->beginTransaction();
        truncateSeedTables($pdo);
        $pdo->commit();
        echo "Tabelas limpas.\n";
        echo "AVISO: parsing_rules foi apagada. Execute a seguir: php src/db/seeder_rules.php\n\n";
    }

    $pdo->beginTransaction();
    seedCategories($pdo, CATEGORIES);
    $pdo->commit();

    $count = count(CATEGORIES);
    echo "\n{$count} categorias inseridas com sucesso.\n";
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Erro: {$exception->getMessage()}\n");
    exit(1);
}

<?php

declare(strict_types=1);

/**
 * Seeder CLI para a tabela parsing_rules.
 *
 * Requer que seeder_categories.php já tenha sido executado.
 *
 * Uso:
 *   php src/db/seeder_rules.php           (aborta se já existirem regras)
 *   php src/db/seeder_rules.php --force   (limpa parsing_rules e reinsere)
 */

require_once __DIR__ . '/Database.php';

// ---------------------------------------------------------------------------
// Dados de seed
// ---------------------------------------------------------------------------

/**
 * Regras de parsing: cada entrada mapeia uma substring a um nome traduzido
 * e ao nome da categoria. A resolução do category_id é feita em runtime.
 *
 * @var array<int, array{substring: string, translated_name: string, category: string}>
 */
const PARSING_RULES = [
    ['substring' => 'pagar me instituicao',              'translated_name' => 'BHBUS',       'category' => 'Transporte'],
    ['substring' => 'gabriella azevedo pires',           'translated_name' => 'Terapia',     'category' => 'Saúde'],
    ['substring' => 'comercial dahana',                  'translated_name' => 'Supernosso',  'category' => 'Supermercado'],
    ['substring' => 'super nosso',                       'translated_name' => 'Supernosso',  'category' => 'Supermercado'],
    ['substring' => 'supermercado',                      'translated_name' => 'Supernosso',  'category' => 'Supermercado'],
    ['substring' => 'sandrarosaria',                     'translated_name' => 'Seu Pedro',   'category' => 'Docinho'],
    ['substring' => 'sandra rosaria cardoso',            'translated_name' => 'Seu Pedro',   'category' => 'Docinho'],
    ['substring' => 'douglas santana arruda',            'translated_name' => 'CEU',         'category' => 'Lazer'],
    ['substring' => 'karine fernanda santana arruda',    'translated_name' => 'CEU',         'category' => 'Lazer'],
    ['substring' => 'karinefernanda',                    'translated_name' => 'CEU',         'category' => 'Lazer'],
    ['substring' => 'jaqueline moreira de carvalho',     'translated_name' => 'Sobrancelha', 'category' => 'Estética'],
    ['substring' => 'rogeria rodrigues de aguiar ribeiro', 'translated_name' => 'Unhas',     'category' => 'Estética'],
    ['substring' => 'uber',                              'translated_name' => 'Transporte',  'category' => 'Transporte'],
    ['substring' => '99',                                'translated_name' => 'Transporte',  'category' => 'Transporte'],
    ['substring' => 'buser',                             'translated_name' => 'Transporte',  'category' => 'Transporte'],
    ['substring' => 'efvm',                              'translated_name' => 'Transporte',  'category' => 'Transporte'],
    ['substring' => 'ifd',                               'translated_name' => 'Fast Food',   'category' => 'Comer Fora'],
    ['substring' => 'burguer king',                      'translated_name' => 'Fast Food',   'category' => 'Comer Fora'],
    ['substring' => 'subway',                            'translated_name' => 'Fast Food',   'category' => 'Comer Fora'],
];

// ---------------------------------------------------------------------------
// Funções auxiliares
// ---------------------------------------------------------------------------

/**
 * Resolve o id de uma categoria pelo nome.
 *
 * @throws \RuntimeException Se a categoria não existir (indica que seeder_categories não foi executado).
 */
function resolveCategoryId(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $name]);
    $row = $stmt->fetch();

    if ($row === false) {
        throw new \RuntimeException(
            "Categoria '{$name}' não encontrada. Execute primeiro: php src/db/seeder_categories.php"
        );
    }

    return (int) $row['id'];
}

/**
 * Verifica se a tabela parsing_rules já tem registos.
 */
function rulesExist(PDO $pdo): bool
{
    $stmt = $pdo->query('SELECT COUNT(*) FROM parsing_rules');

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Insere as regras resolvendo os category_ids em batch antes da inserção.
 *
 * @param array<int, array{substring: string, translated_name: string, category: string}> $rules
 */
function seedRules(PDO $pdo, array $rules): void
{
    // Resolve todos os category_ids antecipadamente para falhar rápido
    // se alguma categoria não existir antes de iniciar inserções.
    $categoryIdCache = [];
    foreach ($rules as $rule) {
        $categoryName = $rule['category'];
        if (!isset($categoryIdCache[$categoryName])) {
            $categoryIdCache[$categoryName] = resolveCategoryId($pdo, $categoryName);
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO parsing_rules (category_id, substring, translated_name, is_active)
         VALUES (:category_id, :substring, :translated_name, :is_active)'
    );

    foreach ($rules as $rule) {
        $categoryId = $categoryIdCache[$rule['category']];
        $stmt->execute([
            ':category_id'    => $categoryId,
            ':substring'      => $rule['substring'],
            ':translated_name' => $rule['translated_name'],
            ':is_active'      => 1,
        ]);
        echo "Regra inserida: \"{$rule['substring']}\" → \"{$rule['translated_name']}\" (categoria: {$rule['category']})\n";
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

    if (rulesExist($pdo)) {
        if (!$isForce) {
            echo "A tabela parsing_rules já contém registos. Use --force para recriar.\n";
            exit(0);
        }

        echo "Modo --force: a limpar parsing_rules...\n";
        $pdo->exec('DELETE FROM parsing_rules');
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'parsing_rules'");
        echo "Tabela limpa.\n\n";
    }

    $pdo->beginTransaction();
    seedRules($pdo, PARSING_RULES);
    $pdo->commit();

    $count = count(PARSING_RULES);
    echo "\n{$count} regras de parsing inseridas com sucesso.\n";
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Erro: {$exception->getMessage()}\n");
    exit(1);
}

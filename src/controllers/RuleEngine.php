<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/db/Database.php';

/**
 * Motor de regras que mapeia descrições brutas de transações para
 * nomes traduzidos e categorias via correspondência de substrings.
 *
 * As regras ativas são carregadas uma única vez no construtor para
 * evitar queries repetidas durante a importação em lote de transações.
 *
 * Exemplo de uso:
 *   $engine = new RuleEngine();
 *   $result = $engine->applyRules('PAGAMENTO UBER TRIP');
 *   // ['translated_description' => 'Transporte', 'category_id' => 1]
 */
final class RuleEngine
{
    /**
     * Regras de parsing ativas, ordenadas por id ASC.
     * A primeira regra que corresponder vence (comportamento determinístico).
     *
     * @var array<int, array{substring: string, translated_name: string, category_id: int}>
     */
    private array $rules;

    /** ID da categoria "Outros", usado como fallback quando nenhuma regra corresponde. */
    private int $othersCategoryId;

    /**
     * @param PDO|null $pdo Conexão PDO opcional (útil em testes); usa o singleton por omissão.
     *
     * @throws \RuntimeException Se a categoria "Outros" não existir na base de dados.
     */
    public function __construct(?PDO $pdo = null)
    {
        $connection = $pdo ?? Database::getConnection();

        $this->othersCategoryId = $this->resolveOthersCategoryId($connection);
        $this->rules            = $this->loadActiveRules($connection);
    }

    /**
     * Aplica as regras de parsing a uma descrição bruta.
     *
     * A correspondência é case-insensitive (mb_stripos / stripos). A primeira regra
     * por ordem de id que coincidir é retornada; em caso de nenhum match,
     * retorna a descrição original e a categoria "Outros".
     *
     * @return array{translated_description: string, category_id: int}
     */
    public function applyRules(string $rawDescription): array
    {
        foreach ($this->rules as $rule) {
            if ($this->containsSubstring($rawDescription, $rule['substring'])) {
                return [
                    'translated_description' => $rule['translated_name'],
                    'category_id'            => $rule['category_id'],
                ];
            }
        }

        return [
            'translated_description' => $rawDescription,
            'category_id'            => $this->othersCategoryId,
        ];
    }

    /**
     * Recarrega as regras da base de dados.
     *
     * Útil quando novas regras são adicionadas em runtime sem recriar
     * a instância do RuleEngine.
     */
    public function reloadRules(?PDO $pdo = null): void
    {
        $connection  = $pdo ?? Database::getConnection();
        $this->rules = $this->loadActiveRules($connection);
    }

    // ---------------------------------------------------------------------------
    // Métodos privados
    // ---------------------------------------------------------------------------

    /**
     * Verifica se $needle ocorre em $haystack, sem distinguir maiúsculas/minúsculas.
     * Usa mb_stripos (UTF-8) quando disponível; caso contrário, stripos (ASCII).
     */
    private function containsSubstring(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
        }

        return stripos($haystack, $needle) !== false;
    }

    /**
     * Carrega todas as regras ativas da tabela parsing_rules, ordenadas por id ASC.
     *
     * @return array<int, array{substring: string, translated_name: string, category_id: int}>
     */
    private function loadActiveRules(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT substring, translated_name, category_id
             FROM parsing_rules
             WHERE is_active = 1
             ORDER BY id ASC'
        );

        return array_map(
            static fn(array $row): array => [
                'substring'      => $row['substring'],
                'translated_name' => $row['translated_name'],
                'category_id'    => (int) $row['category_id'],
            ],
            $stmt->fetchAll()
        );
    }

    /**
     * Resolve o id da categoria "Outros".
     *
     * @throws \RuntimeException Se a categoria não existir.
     */
    private function resolveOthersCategoryId(PDO $pdo): int
    {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = 'Outros' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();

        if ($row === false) {
            throw new \RuntimeException(
                'Categoria "Outros" não encontrada. Execute: php src/db/seeder_categories.php'
            );
        }

        return (int) $row['id'];
    }
}

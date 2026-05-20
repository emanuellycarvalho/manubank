<?php

declare(strict_types=1);

/**
 * Detecta transferências internas (ex.: Pix para si) pelo nome do perfil
 * e garante a categoria "Movimentação interna" na base de dados.
 */
final class InternalTransferService
{
    public const CATEGORY_NAME = 'Movimentação interna';

    private const CATEGORY_TYPE  = 'Neutro';
    private const CATEGORY_COLOR = '#8A8F9E';

    private ?int $categoryId = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Cláusula SQL para excluir lançamentos de movimentação interna dos totais.
     */
    public static function sqlExcludeFromTotals(string $transactionAlias = 't'): string
    {
        $name = self::CATEGORY_NAME;

        return <<<SQL
             AND NOT EXISTS (
                SELECT 1 FROM categories c_exclude
                WHERE c_exclude.id = {$transactionAlias}.category_id
                  AND c_exclude.name = '{$name}'
             )
            SQL;
    }

    /**
     * Garante que a categoria existe e devolve o seu id.
     */
    public function resolveCategoryId(): int
    {
        if ($this->categoryId !== null) {
            return $this->categoryId;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM categories WHERE name = :name LIMIT 1'
        );
        $stmt->execute([':name' => self::CATEGORY_NAME]);
        $row = $stmt->fetch();

        if ($row !== false) {
            $this->categoryId = (int) $row['id'];

            return $this->categoryId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO categories (name, type, color, is_active)
             VALUES (:name, :type, :color, 1)'
        );
        $insert->execute([
            ':name'  => self::CATEGORY_NAME,
            ':type'  => self::CATEGORY_TYPE,
            ':color' => self::CATEGORY_COLOR,
        ]);

        $this->categoryId = (int) $this->pdo->lastInsertId();

        return $this->categoryId;
    }

    /**
     * Verifica se a descrição contém o nome do perfil (case-insensitive, UTF-8).
     */
    public function matchesProfileName(string $description, string $profileName): bool
    {
        $needle = trim($profileName);

        if ($needle === '') {
            return false;
        }

        return $this->containsSubstring($description, $needle);
    }

    /**
     * Marca linhas importadas cuja descrição contém o nome do perfil.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function applyToRows(array $rows, string $profileName): array
    {
        $needle = trim($profileName);

        if ($needle === '') {
            return $rows;
        }

        $categoryId = $this->resolveCategoryId();

        foreach ($rows as $i => $row) {
            $description = (string) ($row['raw_description'] ?? '');

            if (!$this->matchesProfileName($description, $needle)) {
                continue;
            }

            $rows[$i]['category_id']            = $categoryId;
            $rows[$i]['translated_description'] = self::CATEGORY_NAME;
        }

        return $rows;
    }

    private function containsSubstring(string $haystack, string $needle): bool
    {
        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
        }

        return stripos($haystack, $needle) !== false;
    }
}

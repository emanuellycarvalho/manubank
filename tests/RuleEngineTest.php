<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para RuleEngine.
 *
 * Usa PDO em memória (:memory:) para isolar dos ficheiros de dados reais.
 */
final class RuleEngineTest extends TestCase
{
    private \PDO $pdo;
    private \RuleEngine $engine;
    private int $outrosCategoryId;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE categories (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                name      TEXT NOT NULL,
                type      TEXT NOT NULL,
                color     TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE parsing_rules (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id     INTEGER NOT NULL,
                substring       TEXT NOT NULL,
                translated_name TEXT NOT NULL,
                is_active       INTEGER NOT NULL DEFAULT 1
            )
        SQL);

        // Inserir categorias mínimas
        $this->pdo->exec("INSERT INTO categories (name, type, color) VALUES ('Transporte', 'Variável', '#6B8D9E')");
        $catTransporte = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO categories (name, type, color) VALUES ('Lazer', 'Variável', '#A09CD9')");
        $catLazer = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO categories (name, type, color) VALUES ('Outros', 'Variável', '#9C9C9C')");
        $this->outrosCategoryId = (int) $this->pdo->lastInsertId();

        // Inserir regras mínimas
        $stmt = $this->pdo->prepare(
            'INSERT INTO parsing_rules (category_id, substring, translated_name) VALUES (?, ?, ?)'
        );
        $stmt->execute([$catTransporte, 'uber', 'Transporte']);
        $stmt->execute([$catLazer, 'karinefernanda', 'CEU']);
        $stmt->execute([$catTransporte, 'CAFÉ', 'Cafeteria']);

        $this->engine = new \RuleEngine($this->pdo);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function ruleProvider(): array
    {
        return [
            'match uber lowercase'    => ['pagamento uber trip', 'Transporte', false],
            'match uber uppercase'    => ['PAGAMENTO UBER TRIP', 'Transporte', false],
            'match case-insensitive'  => ['karinefernanda recebimento', 'CEU', false],
            'match mixed case'        => ['KARINEFERNANDA PIX', 'CEU', false],
            'match rule uppercase'  => ['compra UBER viagem', 'Transporte', false],
            'match utf8 accent'       => ['pagamento café manhã', 'Cafeteria', false],
            'match utf8 accent upper' => ['PAGAMENTO CAFÉ', 'Cafeteria', false],
            'no match fallback'       => ['LOJA DESCONHECIDA XYZ', 'LOJA DESCONHECIDA XYZ', true],
            'no match empty-like'     => ['abc def', 'abc def', true],
        ];
    }

    #[DataProvider('ruleProvider')]
    public function testApplyRules(string $input, string $expectedTranslated, bool $isFallback): void
    {
        $result = $this->engine->applyRules($input);

        $this->assertSame($expectedTranslated, $result['translated_description']);
        $this->assertIsInt($result['category_id']);

        if ($isFallback) {
            $this->assertSame(
                $this->outrosCategoryId,
                $result['category_id'],
                'Fallback deve usar o category_id de "Outros" da DB, não um ID fixo.'
            );
        } else {
            $this->assertNotSame($this->outrosCategoryId, $result['category_id']);
        }
    }

    public function testThrowsWhenOutrosCategoryMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Outros/');

        $emptyPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $emptyPdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, type TEXT, color TEXT, is_active INTEGER)');
        $emptyPdo->exec('CREATE TABLE parsing_rules (id INTEGER PRIMARY KEY, category_id INTEGER, substring TEXT, translated_name TEXT, is_active INTEGER)');

        new \RuleEngine($emptyPdo);
    }
}

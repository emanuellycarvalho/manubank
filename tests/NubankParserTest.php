<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para NubankParser.
 *
 * Usa PDO em memória para isolar da DB real e testa via parseText()
 * sem dependência de ficheiro PDF.
 */
final class NubankParserTest extends TestCase
{
    private \RuleEngine $engine;
    private \NubankParser $parser;

    protected function setUp(): void
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, type TEXT, color TEXT, is_active INTEGER DEFAULT 1)');
        $pdo->exec('CREATE TABLE parsing_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER, substring TEXT, translated_name TEXT, is_active INTEGER DEFAULT 1)');

        $pdo->exec("INSERT INTO categories (name, type, color) VALUES ('Transporte', 'Variável', '#6B8D9E')");
        $catTransporte = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO categories (name, type, color) VALUES ('Outros', 'Variável', '#9C9C9C')");

        $pdo->exec("INSERT INTO parsing_rules (category_id, substring, translated_name) VALUES ({$catTransporte}, 'uber', 'Transporte')");

        $this->engine = new \RuleEngine($pdo);
        $this->parser = new \NubankParser($this->engine, 2026);
    }

    /**
     * @return array<string, array{string, float, int|null, int|null, string}>
     */
    public static function nubankLineProvider(): array
    {
        return [
            'transacao simples'      => [
                '15 ABR  ••••  1234  Uber Eats             R$ 45,90',
                45.90, null, null, '2026-04-15',
            ],
            'com parcela'            => [
                '02 ABR  ••••  1234  Netflix  - Parcela 2/12  R$ 55,90',
                55.90, 2, 12, '2026-04-02',
            ],
            'valor com milhar'       => [
                '28 ABR  ••••  1234  Farmacia  - Parcela 1/3  R$ 1.200,00',
                1200.00, 1, 3, '2026-04-28',
            ],
            'sem match no ruleengine' => [
                '10 MAI  ••••  9999  Loja Desconhecida ABC  R$ 12,00',
                12.00, null, null, '2026-05-10',
            ],
        ];
    }

    #[DataProvider('nubankLineProvider')]
    public function testParseText(
        string $line,
        float $expectedAmount,
        ?int $expectedInstallCurr,
        ?int $expectedInstallTot,
        string $expectedDate
    ): void {
        $rows = $this->parser->parseText($line);

        $this->assertCount(1, $rows, 'Deve extrair exatamente 1 transação.');

        $row = $rows[0];
        $this->assertSame('saída', $row['type']);
        $this->assertSame('Nubank', $row['origin']);
        $this->assertSame('Credito', $row['operation']);
        $this->assertEqualsWithDelta($expectedAmount, $row['amount'], 0.001);
        $this->assertSame($expectedDate, $row['date']);
        $this->assertSame($expectedInstallCurr, $row['installment_current']);
        $this->assertSame($expectedInstallTot, $row['installment_total']);
        $this->assertSame(substr($expectedDate, 0, 7), $row['month_year']);
        $this->assertIsInt($row['category_id']);
        $this->assertNotEmpty($row['translated_description']);
    }

    public function testParseTextWithUberAppliesRule(): void
    {
        $rows = $this->parser->parseText('15 ABR  ••••  1234  Uber Trip  R$ 30,00');

        $this->assertSame('Transporte', $rows[0]['translated_description']);
    }

    public function testParseTextNoMatchFallsBackToRawDescription(): void
    {
        $rows = $this->parser->parseText('15 ABR  ••••  1234  Loja XYZ Desconhecida  R$ 10,00');

        $this->assertSame('Loja XYZ Desconhecida', $rows[0]['translated_description']);
    }

    public function testParseTextEmptyTextReturnsEmptyArray(): void
    {
        $rows = $this->parser->parseText('Sem transacoes aqui.');

        $this->assertSame([], $rows);
    }

    public function testMultipleTransactionsExtracted(): void
    {
        $text = file_get_contents(__DIR__ . '/fixtures/nubank_sample.txt');
        $rows = $this->parser->parseText($text);

        $this->assertCount(5, $rows);
    }

    public function testParseThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->parser->parse('/caminho/inexistente/fatura.pdf');
    }
}

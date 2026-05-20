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
            // --- Formato original (•••• como U+2022) ---
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
            // --- Formato real do PDF (single space, MAR/ABR PT-BR) ---
            'real pdf parcela'        => [
                '29 MAR •••• 1470 Mercadolivre*Mercadol - Parcela 6/12 R$ 286,58',
                286.58, 6, 12, '2026-03-29',
            ],
            'real pdf uber'           => [
                '02 ABR •••• 8812 Dl *Uber*Rides R$ 5,91',
                5.91, null, null, '2026-04-02',
            ],
            'real pdf 99'             => [
                '02 ABR •••• 8812 Pg *99 Ride R$ 7,06',
                7.06, null, null, '2026-04-02',
            ],
            'real pdf bhbus'          => [
                '02 ABR •••• 1470 Transfacil*Bhbus R$ 12,50',
                12.50, null, null, '2026-04-02',
            ],
            // --- Variante com asteriscos (extração alternativa de PDF) ---
            'mascara asteriscos'      => [
                '15 ABR **** 1234 Amazon R$ 99,90',
                99.90, null, null, '2026-04-15',
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

    public function testRealUserFormatBlock(): void
    {
        $text = <<<'TEXT'
29 MAR •••• 1470 Mercadolivre*Mercadol - Parcela 6/12 R$ 286,58
02 ABR •••• 8812 Dl *Uber*Rides R$ 5,91
02 ABR •••• 8812 Pg *99 Ride R$ 7,06
02 ABR •••• 8812 Pg *99 Ride R$ 12,20
02 ABR •••• 1470 Transfacil*Bhbus R$ 12,50
TEXT;

        $rows = $this->parser->parseText($text);

        $this->assertCount(5, $rows, 'Deve extrair as 5 transações do formato real do PDF Nubank.');
        $this->assertSame('2026-03-29', $rows[0]['date']);
        $this->assertEqualsWithDelta(286.58, $rows[0]['amount'], 0.001);
        $this->assertSame(6, $rows[0]['installment_current']);
        $this->assertSame(12, $rows[0]['installment_total']);
        $this->assertSame('2026-04-02', $rows[1]['date']);
        $this->assertEqualsWithDelta(5.91, $rows[1]['amount'], 0.001);
        $this->assertSame('saída', $rows[1]['type']);
        $this->assertSame('Nubank', $rows[1]['origin']);
    }

    public function testParseThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->parser->parse('/caminho/inexistente/fatura.pdf');
    }

    public function testParseTextEstornoRefund(): void
    {
        $text = <<<'TEXT'
08 MAR •••• 8812 Dl*99 Ride R$ 13,75
08 MAR Estorno de "Pg *Tembici"

Estorno referente a compra em Pg *Tembici, de valor R$ 9,99, realizada em
03 de Março de 2026

−R$ 9,99
TEXT;

        $rows = $this->parser->parseText($text);

        $this->assertCount(2, $rows, 'Deve extrair a compra e o estorno.');

        $charge = $rows[0];
        $this->assertSame('saída', $charge['type']);
        $this->assertEqualsWithDelta(13.75, $charge['amount'], 0.001);

        $refund = $rows[1];
        $this->assertSame('entrada', $refund['type']);
        $this->assertSame('Estorno', $refund['operation']);
        $this->assertSame('Estorno de "Pg *Tembici"', $refund['raw_description']);
        $this->assertEqualsWithDelta(9.99, $refund['amount'], 0.001);
        $this->assertSame('2026-03-08', $refund['date']);
        $this->assertSame('2026-03', $refund['month_year']);
        $this->assertNull($refund['installment_current']);
    }

    public function testParseTextEstornoWithHyphenMinusAmount(): void
    {
        $text = <<<'TEXT'
10 ABR Estorno de "Uber Trip"

Estorno referente a compra em Uber Trip, de valor R$ 5,00, realizada em
01 de Abril de 2026

-R$ 5,00
TEXT;

        $rows = $this->parser->parseText($text);

        $this->assertCount(1, $rows);
        $this->assertSame('entrada', $rows[0]['type']);
        $this->assertEqualsWithDelta(5.00, $rows[0]['amount'], 0.001);
    }

    public function testParseTextEstornoAmountFromDetailWhenNoMinusLine(): void
    {
        $text = <<<'TEXT'
15 MAI Estorno de "Loja XYZ"

Estorno referente a compra em Loja XYZ, de valor R$ 12,50, realizada em
10 de Maio de 2026
TEXT;

        $rows = $this->parser->parseText($text);

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(12.50, $rows[0]['amount'], 0.001);
        $this->assertSame('entrada', $rows[0]['type']);
    }

    public function testParseTextDescontoAntecipacao(): void
    {
        $text = <<<'TEXT'
05 FEV •••• 1470 Antecipada - Mercadolivre*Mercadol - Parcela 4/12 R$ 286,58
05 FEV Desconto Antecipação Mercadolivre*Mercadol −R$ 20,82
TEXT;

        $rows = $this->parser->parseText($text);

        $this->assertCount(2, $rows, 'Deve extrair a antecipação (saída) e o desconto (entrada).');

        $charge = $rows[0];
        $this->assertSame('saída', $charge['type']);
        $this->assertEqualsWithDelta(286.58, $charge['amount'], 0.001);
        $this->assertSame(4, $charge['installment_current']);
        $this->assertSame(12, $charge['installment_total']);

        $discount = $rows[1];
        $this->assertSame('entrada', $discount['type']);
        $this->assertSame('Desconto Antecipação', $discount['operation']);
        $this->assertSame('Desconto Antecipação Mercadolivre*Mercadol', $discount['raw_description']);
        $this->assertEqualsWithDelta(20.82, $discount['amount'], 0.001);
        $this->assertSame('2026-02-05', $discount['date']);
    }
}

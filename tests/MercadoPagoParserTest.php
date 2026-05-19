<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para MercadoPagoParser.
 *
 * Usa PDO em memória e ficheiros CSV temporários para isolar da DB real.
 */
final class MercadoPagoParserTest extends TestCase
{
    private \RuleEngine $engine;
    private \MercadoPagoParser $parser;

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
        $this->parser = new \MercadoPagoParser($this->engine);
    }

    /**
     * Cria um ficheiro CSV temporário com as linhas fornecidas e retorna o caminho.
     * O separador de campo é ponto e vírgula (;), conforme o formato Mercado Pago Brasil.
     *
     * @param list<string> $lines Linhas do CSV incluindo o cabeçalho.
     */
    private function createTempCsv(array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mp_test_') . '.csv';
        file_put_contents($path, implode("\n", $lines));

        return $path;
    }

    // ---------------------------------------------------------------------------
    // DataProviders
    // ---------------------------------------------------------------------------

    /**
     * @return array<string, array{string, string, float, string}>
     * Parâmetros: csvLine, expectedType, expectedAmount, expectedDate
     */
    public static function singleRowProvider(): array
    {
        return [
            'saída valor negativo'   => [
                '01-04-2026;Pix enviado João Silva;;-150,00',
                'saída', 150.00, '2026-04-01',
            ],
            'entrada valor positivo' => [
                '10-04-2026;Pix recebido Maria Souza;;500,00',
                'entrada', 500.00, '2026-04-10',
            ],
            'saída valor pequeno'    => [
                '15-04-2026;Pix enviado Uber corrida;;-28,90',
                'saída', 28.90, '2026-04-15',
            ],
        ];
    }

    #[DataProvider('singleRowProvider')]
    public function testTypeAndAmountFromSingleRow(
        string $csvLine,
        string $expectedType,
        float $expectedAmount,
        string $expectedDate
    ): void {
        $path = $this->createTempCsv(['Data,Descricao,Tipo,Valor', $csvLine]);

        try {
            $rows = $this->parser->parse($path);
        } finally {
            unlink($path);
        }

        $this->assertCount(1, $rows);
        $this->assertSame($expectedType, $rows[0]['type']);
        $this->assertEqualsWithDelta($expectedAmount, $rows[0]['amount'], 0.001);
        $this->assertSame($expectedDate, $rows[0]['date']);
        $this->assertSame(substr($expectedDate, 0, 7), $rows[0]['month_year']);
        $this->assertSame('MercadoPago', $rows[0]['origin']);
    }

    // ---------------------------------------------------------------------------
    // Testes de limpeza de prefixos
    // ---------------------------------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     * Parâmetros: descrição original, raw_description esperada após limpeza
     */
    public static function prefixCleaningProvider(): array
    {
        return [
            'prefixo pix enviado'               => ['Pix enviado João Silva',              'João Silva'],
            'prefixo pix recebido'              => ['Pix recebido Maria Souza',             'Maria Souza'],
            'prefixo pix qr'                    => ['Pagamento com QR Pix Padaria',         'Padaria'],
            'prefixo transferência pix enviada' => ['Transferência Pix enviada Banco XYZ',  'Banco XYZ'],
            'prefixo transferência pix recebida'=> ['Transferência Pix recebida Maria Souza', 'Maria Souza'],
            'sem prefixo reconhecido'           => ['Pix Loja Online',                      'Pix Loja Online'],
        ];
    }

    #[DataProvider('prefixCleaningProvider')]
    public function testDescriptionPrefixCleaning(string $rawOp, string $expectedRawDescription): void
    {
        $csvLine = "01-04-2026;{$rawOp};;-10,00";
        $path    = $this->createTempCsv(['Data;Descricao;Tipo;Valor', $csvLine]);

        try {
            $rows = $this->parser->parse($path);
        } finally {
            unlink($path);
        }

        $this->assertCount(1, $rows);
        $this->assertSame($expectedRawDescription, $rows[0]['raw_description']);
        // operation é sempre 'Pix' para transações MercadoPago
        $this->assertSame('Pix', $rows[0]['operation']);
    }

    // ---------------------------------------------------------------------------
    // Testes de comportamento especial
    // ---------------------------------------------------------------------------

    public function testRowWithAmountZeroIsSkipped(): void
    {
        $path = $this->createTempCsv([
            'Cabeçalho irrelevante',
            '20-04-2026;Pix enviado Saldo;;0,00',
        ]);

        try {
            $rows = $this->parser->parse($path);
        } finally {
            unlink($path);
        }

        $this->assertSame([], $rows);
    }

    public function testRowWithInvalidDateIsSkipped(): void
    {
        $path = $this->createTempCsv([
            'Cabeçalho irrelevante',
            'invalid-date;Pix enviado Teste;;-10,00',
        ]);

        try {
            $rows = $this->parser->parse($path);
        } finally {
            unlink($path);
        }

        $this->assertSame([], $rows);
    }

    public function testUberRuleApplied(): void
    {
        $path = $this->createTempCsv([
            'Cabeçalho irrelevante',
            '15-04-2026;Pix enviado Uber corrida 15/04;;-28,90',
        ]);

        try {
            $rows = $this->parser->parse($path);
        } finally {
            unlink($path);
        }

        $this->assertSame('Transporte', $rows[0]['translated_description']);
    }

    public function testInstallmentFieldsAreAlwaysNull(): void
    {
        $path = $this->createTempCsv([
            'Cabeçalho irrelevante',
            '01-04-2026;Pix enviado Qualquer coisa;;-50,00',
        ]);

        try {
            $rows = $this->parser->parse($path);
        } finally {
            unlink($path);
        }

        $this->assertNull($rows[0]['installment_current']);
        $this->assertNull($rows[0]['installment_total']);
    }

    public function testFullFixtureFile(): void
    {
        $rows = $this->parser->parse(__DIR__ . '/fixtures/mercadopago_sample.csv');

        // fixture: 7 linhas com data, apenas 5 contêm "pix" → importadas
        // Rendimentos e Dinheiro retirado são descartados
        $this->assertCount(5, $rows);

        // Todas as operações devem ser "Pix"
        foreach ($rows as $row) {
            $this->assertSame('Pix', $row['operation']);
        }
    }

    public function testRendimentoIsIgnored(): void
    {
        $path = $this->createTempCsv([
            'Cabeçalho irrelevante',
            '20-04-2026;Rendimentos do período;EXT-999;12,75',
        ]);

        try {
            $rows = $this->parser->parse($path);
        } finally {
            unlink($path);
        }

        $this->assertCount(0, $rows);
    }

    public function testNonPixIsIgnored(): void
    {
        $path = $this->createTempCsv([
            'Cabeçalho irrelevante',
            '25-04-2026;Dinheiro retirado Caixa;MP-006;-200,00',
        ]);

        try {
            $rows = $this->parser->parse($path);
        } finally {
            unlink($path);
        }

        $this->assertCount(0, $rows);
    }

    public function testOperationIsAlwaysPix(): void
    {
        $path = $this->createTempCsv([
            'Cabeçalho irrelevante',
            '01-04-2026;Pix enviado João Silva;MP-001;-50,00',
        ]);

        try {
            $rows = $this->parser->parse($path);
        } finally {
            unlink($path);
        }

        $this->assertCount(1, $rows);
        $this->assertSame('Pix', $rows[0]['operation']);
    }

    public function testExternalIdExtracted(): void
    {
        $path = $this->createTempCsv([
            'Cabeçalho irrelevante',
            '01-04-2026;Pix enviado João Silva;REF-123;-50,00',
        ]);

        try {
            $rows = $this->parser->parse($path);
        } finally {
            unlink($path);
        }

        $this->assertCount(1, $rows);
        $this->assertSame('REF-123', $rows[0]['external_id']);
    }

    public function testHeaderLinesAreSkipped(): void
    {
        // Simula o formato real com várias linhas de resumo antes das transações
        $path = $this->createTempCsv([
            'Mercado Pago - Extrato',
            'Período: 01/04/2026 a 30/04/2026',
            'Titular: Fulano',
            '',
            'RELEASE_DATE;TRANSACTION_TYPE;REFERENCE_ID;NET_CREDIT_AMOUNT',
            '05-04-2026;Pix enviado Loja;REF-001;-25,00',
        ]);

        try {
            $rows = $this->parser->parse($path);
        } finally {
            unlink($path);
        }

        $this->assertCount(1, $rows);
        $this->assertSame('2026-04-05', $rows[0]['date']);
    }

    public function testThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->parser->parse('/caminho/inexistente/extrato.csv');
    }
}

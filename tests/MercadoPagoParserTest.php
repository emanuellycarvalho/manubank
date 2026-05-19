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
                '01-04-2026;João Silva;;-150,00',
                'saída', 150.00, '2026-04-01',
            ],
            'entrada valor positivo' => [
                '10-04-2026;Maria Souza;;500,00',
                'entrada', 500.00, '2026-04-10',
            ],
            'saída valor pequeno'    => [
                '15-04-2026;Uber corrida;;-28,90',
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
            'prefixo transferência enviada'    => ['Transferência enviada João Silva', 'João Silva'],
            'prefixo pix qr'                   => ['Pagamento com QR Pix Padaria', 'Padaria'],
            'prefixo pagamento efetuado'       => ['Pagamento efetuado para Serviço', 'Serviço'],
            'prefixo transferência recebida'   => ['Transferência recebida de Maria', 'Maria'],
            'sem prefixo'                      => ['Uber corrida', 'Uber corrida'],
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
        // operation deve manter o valor original (antes da limpeza)
        $this->assertSame($rawOp, $rows[0]['operation']);
    }

    // ---------------------------------------------------------------------------
    // Testes de comportamento especial
    // ---------------------------------------------------------------------------

    public function testRowWithAmountZeroIsSkipped(): void
    {
        $path = $this->createTempCsv([
            'Data;Descricao;Tipo;Valor',
            '20-04-2026;Separador de saldo;;0,00',
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
            'Data;Descricao;Tipo;Valor',
            'invalid-date;Teste;;-10,00',
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
            'Data;Descricao;Tipo;Valor',
            '15-04-2026;Uber corrida 15/04;;-28,90',
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
            'Data;Descricao;Tipo;Valor',
            '01-04-2026;Qualquer coisa;;-50,00',
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

        // fixture tem 5 linhas: 4 válidas + 1 com valor 0 (ignorada)
        $this->assertCount(4, $rows);
    }

    public function testThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->parser->parse('/caminho/inexistente/extrato.csv');
    }
}

<?php

declare(strict_types=1);

/**
 * Parser de extratos do Mercado Pago em formato CSV.
 *
 * Lê o ficheiro CSV linha a linha, determina o tipo (entrada/saída) pelo sinal
 * do valor, limpa prefixos de operação da descrição e aplica o RuleEngine
 * para categorizar cada transação.
 *
 * Formato esperado do CSV:
 *   Coluna 0 — Data (formato d-m-Y, ex: "01-04-2026")
 *   Coluna 1 — Operação/Descrição (ex: "Transferência enviada João Silva")
 *   Coluna 2 — (ignorada)
 *   Coluna 3 — Valor (ex: "-45,90" ou "1200,00")
 *
 * Uso:
 *   $parser = new MercadoPagoParser($ruleEngine);
 *   $rows   = $parser->parse('/path/to/extrato.csv');
 */
final class MercadoPagoParser
{
    /**
     * Prefixos removidos da descrição antes de passar ao RuleEngine.
     * O array é intencional extensível — adicionar entradas sem alterar a lógica.
     *
     * @var list<string>
     */
    private const DESCRIPTION_PREFIXES = [
        'Transferência enviada ',
        'Transferência recebida de ',
        'Pagamento com QR Pix ',
        'Pagamento efetuado para ',
    ];

    /**
     * @param RuleEngine $ruleEngine Motor de regras para categorização.
     */
    public function __construct(private readonly RuleEngine $ruleEngine)
    {
    }

    /**
     * Lê e processa um ficheiro CSV de extrato do Mercado Pago.
     *
     * Linhas com data inválida ou valor zero são silenciosamente ignoradas
     * (contabilizadas em `$skipped` pelo ImportController).
     *
     * @return array<int, array{category_id: int, type: string, date: string, origin: string, operation: string, amount: float, raw_description: string, translated_description: string, installment_current: null, installment_total: null, month_year: string}>
     *
     * @throws \RuntimeException Se o ficheiro não puder ser aberto.
     */
    public function parse(string $csvPath): array
    {
        $handle = @fopen($csvPath, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Não foi possível abrir o ficheiro CSV: {$csvPath}");
        }

        try {
            return $this->readCsv($handle);
        } finally {
            fclose($handle);
        }
    }

    // ---------------------------------------------------------------------------
    // Métodos privados
    // ---------------------------------------------------------------------------

    /**
     * Lê o handle CSV e retorna os registos formatados.
     * Salta a primeira linha (cabeçalho) automaticamente.
     * Usa ponto e vírgula (;) como separador de campo.
     *
     * @param  resource $handle
     * @return array<int, array{...}>
     */
    private function readCsv($handle): array
    {
        // Saltar cabeçalho
        // $escape='' desativa o escape de backslash (RFC 4180 puro); obrigatório no PHP 8.4+.
        fgetcsv($handle, 0, ';', '"', '');

        $rows = [];

        while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            if (count($row) < 4) {
                continue;
            }

            $record = $this->processRow($row);

            if ($record === null) {
                continue;
            }

            $rows[] = $record;
        }

        return $rows;
    }

    /**
     * Processa uma linha do CSV e retorna o registo formatado, ou null se inválida.
     *
     * @param  list<string> $row
     * @return array{category_id: int, type: string, date: string, origin: string, operation: string, amount: float, raw_description: string, translated_description: string, installment_current: null, installment_total: null, month_year: string}|null
     */
    private function processRow(array $row): ?array
    {
        // --- Data (coluna 0) ---
        $dateObj = \DateTime::createFromFormat('d-m-Y', trim($row[0]));

        if ($dateObj === false) {
            return null;
        }

        $date      = $dateObj->format('Y-m-d');
        $monthYear = $dateObj->format('Y-m');

        // --- Valor (coluna 3) ---
        $amount = (float) str_replace(',', '.', trim($row[3]));

        if ($amount === 0.0) {
            return null;
        }

        $type   = $amount < 0.0 ? 'saída' : 'entrada';
        $amount = abs($amount);

        // --- Descrição (coluna 1) ---
        $rawOperation   = trim($row[1]);
        $rawDescription = str_ireplace(self::DESCRIPTION_PREFIXES, '', $rawOperation);
        $rawDescription = trim($rawDescription);

        $ruleResult = $this->ruleEngine->applyRules($rawDescription);

        return [
            'category_id'            => $ruleResult['category_id'],
            'type'                   => $type,
            'date'                   => $date,
            'origin'                 => 'MercadoPago',
            'operation'              => $rawOperation,
            'amount'                 => $amount,
            'raw_description'        => $rawDescription,
            'translated_description' => $ruleResult['translated_description'],
            'installment_current'    => null,
            'installment_total'      => null,
            'month_year'             => $monthYear,
        ];
    }
}

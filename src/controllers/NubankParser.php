<?php

declare(strict_types=1);

/**
 * Parser de faturas do cartão Nubank em formato PDF.
 *
 * Extrai transações de crédito do texto do PDF usando uma regex estruturada,
 * converte datas do formato PT (DD MÊS) para ISO 8601 e aplica o RuleEngine
 * para categorizar cada linha.
 *
 * Uso:
 *   $parser = new NubankParser($ruleEngine);
 *   $rows   = $parser->parse('/path/to/fatura.pdf');
 */
final class NubankParser
{
    /**
     * Regex para capturar linhas de transação do texto extraído do PDF Nubank.
     *
     * Grupos:
     *   1 - data bruta (ex: "15 ABR" ou "15 abr")
     *   2 - 4 últimos dígitos do cartão (descartado)
     *   3 - descrição bruta
     *   4 - parcela atual (nullable)
     *   5 - parcela total (nullable)
     *   6 - valor (formato brasileiro, ex: "1.234,56")
     *
     * Notas de robustez:
     *   - A máscara do cartão (normalmente "••••") é aceite como qualquer sequência
     *     de 1–8 caracteres não-numéricos não-espaço ([^\s\d]+). Isto torna o parser
     *     imune a diferenças de codificação (•, *, · ou outros) entre versões do PDF.
     *   - O separador "Parcela" aceita maiúsculas e minúsculas.
     *   - O separador "R$" aceita espaço não-separável (NBSP U+00A0) além do espaço normal.
     */
    private const TRANSACTION_REGEX =
        '/(\d{2}\s[a-zA-ZÀ-ú]{3})\s+[^\s\d]+\s+(\d{4})\s+(.+?)(?:\s+-\s+Parcela\s+(\d+)\/(\d+))?\s+R\$[\s\x{00A0}]+([\d,.]+)/u';

    /**
     * Mapa de abreviações de meses em português para números MM.
     *
     * @var array<string, string>
     */
    /**
     * Mapa de abreviações de meses em português e inglês para números MM.
     * Cobre variações encontradas em PDFs Nubank (ex: "MAR" = Março, não March).
     *
     * @var array<string, string>
     */
    private const MONTH_MAP = [
        // Português (PT-BR)
        'JAN' => '01', 'FEV' => '02', 'MAR' => '03', 'ABR' => '04',
        'MAI' => '05', 'JUN' => '06', 'JUL' => '07', 'AGO' => '08',
        'SET' => '09', 'OUT' => '10', 'NOV' => '11', 'DEZ' => '12',
        // Inglês (fallback, caso o PDF seja extraído com locale diferente)
        'FEB' => '02', 'APR' => '04', 'MAY' => '05', 'AUG' => '08',
        'SEP' => '09', 'OCT' => '10',
    ];

    /**
     * @param RuleEngine $ruleEngine Motor de regras para categorização.
     * @param int        $year       Ano a usar na data; 0 usa o ano corrente.
     */
    public function __construct(
        private readonly RuleEngine $ruleEngine,
        private readonly int $year = 0
    ) {
    }

    /**
     * Lê e processa um ficheiro PDF de fatura Nubank.
     *
     * @return array<int, array{category_id: int, type: string, date: string, origin: string, operation: string, amount: float, raw_description: string, translated_description: string, installment_current: int|null, installment_total: int|null, month_year: string}>
     *
     * @throws \RuntimeException Se o ficheiro não existir.
     */
    public function parse(string $pdfPath): array
    {
        if (!file_exists($pdfPath)) {
            throw new \RuntimeException("Ficheiro PDF não encontrado: {$pdfPath}");
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($pdfPath);
        $text   = $pdf->getText();

        return $this->parseText($text);
    }

    /**
     * Processa o texto bruto extraído de um PDF Nubank.
     *
     * Método público para permitir testes unitários sem dependência de ficheiro.
     *
     * @return array<int, array{category_id: int, type: string, date: string, origin: string, operation: string, amount: float, raw_description: string, translated_description: string, installment_current: int|null, installment_total: int|null, month_year: string}>
     */
    public function parseText(string $text): array
    {
        $matches = [];
        preg_match_all(self::TRANSACTION_REGEX, $text, $matches, PREG_SET_ORDER);

        $rows = [];
        $year = $this->year !== 0 ? $this->year : (int) date('Y');

        foreach ($matches as $match) {
            $rawDate     = $match[1];              // ex: "15 ABR"
            $rawDesc     = trim($match[3]);
            $installCurr = $match[4] !== '' ? (int) $match[4] : null;
            $installTot  = $match[5] !== '' ? (int) $match[5] : null;
            $amount      = $this->parseAmount($match[6]);

            $date      = $this->formatDate($rawDate, $year);
            $monthYear = substr($date, 0, 7); // "YYYY-MM"

            $ruleResult = $this->ruleEngine->applyRules($rawDesc);

            $rows[] = [
                'category_id'            => $ruleResult['category_id'],
                'type'                   => 'saída',
                'date'                   => $date,
                'origin'                 => 'Nubank',
                'operation'              => 'Credito',
                'amount'                 => $amount,
                'raw_description'        => $rawDesc,
                'translated_description' => $ruleResult['translated_description'],
                'installment_current'    => $installCurr,
                'installment_total'      => $installTot,
                'month_year'             => $monthYear,
            ];
        }

        return $rows;
    }

    // ---------------------------------------------------------------------------
    // Métodos privados
    // ---------------------------------------------------------------------------

    /**
     * Converte uma data bruta em PT (ex: "15 ABR") para ISO 8601 (ex: "2026-04-15").
     *
     * @throws \RuntimeException Se o mês não for reconhecido.
     */
    private function formatDate(string $rawDate, int $year): string
    {
        [$day, $monthAbbr] = explode(' ', trim($rawDate));
        $monthAbbr = strtoupper($monthAbbr);

        if (!isset(self::MONTH_MAP[$monthAbbr])) {
            throw new \RuntimeException("Mês não reconhecido: '{$monthAbbr}'");
        }

        return sprintf('%04d-%s-%02d', $year, self::MONTH_MAP[$monthAbbr], (int) $day);
    }

    /**
     * Converte um valor em formato brasileiro (ex: "1.234,56") para float.
     */
    private function parseAmount(string $raw): float
    {
        return (float) str_replace(['.', ','], ['', '.'], $raw);
    }
}

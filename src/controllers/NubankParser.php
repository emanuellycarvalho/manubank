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
     * Regex estrita para linhas de transação do extrato Nubank (formato real do PDF).
     *
     * Grupos:
     *   1 - data bruta (ex: "29 AGO")
     *   2 - 4 últimos dígitos do cartão (descartado)
     *   3 - descrição bruta
     *   4 - parcela atual (nullable)
     *   5 - parcela total (nullable)
     *   6 - valor (formato brasileiro, ex: "1.234,56")
     */
    private const TRANSACTION_REGEX =
        '/(?m)^(\d{2}\s[a-zA-Z]{3})\s+(?:••••|\*{4})\s+(\d{4})\s*(.+?)(?:\s+-\s+Parcela\s+(\d+)\/(\d+))?\s+R\$\s*([\d,.]+)/u';

    /**
     * Cabeçalho de estorno/reembolso (crédito na fatura).
     *
     * Ex.: 08 MAR Estorno de "Pg *Tembici"
     * O valor costuma vir em linhas seguintes (−R$ 9,99 ou "de valor R$ 9,99").
     */
    private const ESTORNO_HEADER_REGEX =
        '/(\d{2}\s[a-zA-ZÀ-ú]{3})\s+Estorno\s+de\s+"([^"]+)"/iu';

    /**
     * Desconto por antecipação de parcela (crédito na fatura).
     *
     * Ex.: 05 FEV Desconto Antecipação Mercadolivre*Mercadol −R$ 20,82
     */
    private const DESCONTO_ANTECIPACAO_REGEX =
        '/(\d{2}\s[a-zA-ZÀ-ú]{3})\s+Desconto\s+Antecipa\S*\s+(.+?)\s+[−\-]\s*R\$[\s\x{00A0}]*([\d,.]+)/iu';

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
     * Data de vencimento no nome do ficheiro (ex.: Nubank_2026-01-06.pdf).
     *
     * @return array{0: int, 1: int} [ano, mês] do vencimento
     *
     * @throws \RuntimeException Se o nome do ficheiro não seguir o padrão esperado.
     */
    public static function dueDateFromFilename(string $filename): array
    {
        $base = basename($filename);

        if (preg_match('/Nubank[_-](\d{4})-(\d{2})-\d{2}(?:\.pdf)?$/i', $base, $m)) {
            $year  = (int) $m[1];
            $month = (int) $m[2];
            if ($year >= 2000 && $year <= 2099 && $month >= 1 && $month <= 12) {
                return [$year, $month];
            }
        }

        throw new \RuntimeException(
            'Nome do PDF inválido. Renomeie para o formato Nubank_AAAA-MM-DD.pdf (ex.: Nubank_2025-10-06.pdf).'
        );
    }

    /**
     * Ano das transações: cruza vencimento (nome do ficheiro) com fim do período da fatura (texto do PDF).
     *
     * Faturas de nov/dez costumam vencer em jan do ano seguinte (Nubank_2026-01-06.pdf com compras em DEZ).
     *
     * @throws \RuntimeException Se o nome do ficheiro não seguir o padrão esperado.
     */
    public static function resolveTransactionYear(string $pdfText, string $filename): int
    {
        [$dueYear, $dueMonth] = self::dueDateFromFilename($filename);
        $periodEndMonth       = self::parsePeriodEndMonth($pdfText);

        if ($periodEndMonth === null || !isset(self::MONTH_MAP[$periodEndMonth])) {
            return $dueYear;
        }

        $endMonthNum = (int) self::MONTH_MAP[$periodEndMonth];

        // Período termina em mês "depois" do vencimento no calendário → compras são do ano anterior.
        if ($endMonthNum > $dueMonth) {
            return $dueYear - 1;
        }

        return $dueYear;
    }

    /**
     * Lê e processa um ficheiro PDF de fatura Nubank.
     *
     * @return array<int, array{category_id: int, type: string, date: string, origin: string, operation: string, amount: float, raw_description: string, translated_description: string, installment_current: int|null, installment_total: int|null, month_year: string}>
     *
     * @throws \RuntimeException Se o ficheiro não existir, não for legível ou não tiver transações.
     */
    public function parse(string $pdfPath, string $originalFilename = ''): array
    {
        if (!is_readable($pdfPath)) {
            throw new \RuntimeException("Ficheiro PDF não encontrado ou ilegível: {$pdfPath}");
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($pdfPath);
            $text   = trim($pdf->getText());
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Não foi possível ler o PDF da fatura Nubank: ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($text === '') {
            throw new \RuntimeException(
                'O PDF não contém texto extraível. Tente exportar novamente ou use colar texto.'
            );
        }

        if ($this->countChargeMatches($text) === 0) {
            throw new \RuntimeException(
                'Nenhuma transação reconhecida no PDF. Verifique se é a fatura do cartão Nubank no formato: "DD MMM •••• DDDD Descrição R$ valor".'
            );
        }

        $filename = $originalFilename !== '' ? $originalFilename : basename($pdfPath);
        $year     = self::resolveTransactionYear($text, $filename);
        $scoped   = new self($this->ruleEngine, $year);

        return $scoped->parseText($text);
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
        $text = $this->normalizeExtractedText($text);

        return array_merge(
            $this->parseChargeLines($text),
            $this->parseRefundBlocks($text),
            $this->parseEarlyPaymentDiscounts($text),
        );
    }

    /**
     * Linhas de compra no formato clássico (data + cartão + descrição + R$).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseChargeLines(string $text): array
    {
        $matches = [];
        preg_match_all(self::TRANSACTION_REGEX, $text, $matches, PREG_SET_ORDER);

        $rows = [];
        $year = $this->year !== 0 ? $this->year : (int) date('Y');

        foreach ($matches as $match) {
            $rawDate     = $match[1];
            $rawDesc = trim($match[3]);

            if ($this->isNonChargeLine($rawDesc)) {
                continue;
            }

            $installCurr = isset($match[4]) && $match[4] !== '' ? (int) $match[4] : null;
            $installTot  = isset($match[5]) && $match[5] !== '' ? (int) $match[5] : null;
            $amount      = $this->parseAmount($match[6]);

            $date      = $this->formatDate($rawDate, $year);
            $monthYear = substr($date, 0, 7);

            $ruleResult = $this->ruleEngine->applyRules($rawDesc);

            $rows[] = [
                'category_id'            => $ruleResult['category_id'],
                'type'                   => 'saída',
                'date'                   => $date,
                'origin'                 => 'Nubank Fatura',
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

    /**
     * Blocos de estorno/reembolso (crédito na fatura — reduz o total da fatura).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseRefundBlocks(string $text): array
    {
        if (!preg_match_all(self::ESTORNO_HEADER_REGEX, $text, $headers, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $rows = [];
        $year = $this->year !== 0 ? $this->year : (int) date('Y');
        $count = count($headers[0]);

        for ($i = 0; $i < $count; $i++) {
            $rawDate  = $headers[1][$i][0];
            $merchant = trim($headers[2][$i][0]);
            $start    = $headers[0][$i][1] + strlen($headers[0][$i][0]);
            $end      = ($i + 1 < $count) ? $headers[0][$i + 1][1] : strlen($text);
            $block    = substr($text, $start, $end - $start);

            $amount = $this->parseRefundAmount($block);
            if ($amount === null || $amount <= 0.0) {
                continue;
            }

            $rawDesc    = 'Estorno de "' . $merchant . '"';
            $date       = $this->formatDate($rawDate, $year);
            $monthYear  = substr($date, 0, 7);
            $ruleResult = $this->ruleEngine->applyRules($merchant);

            $rows[] = [
                'category_id'            => $ruleResult['category_id'],
                'type'                   => 'entrada',
                'date'                   => $date,
                'origin'                 => 'Nubank',
                'operation'              => 'Estorno',
                'amount'                 => $amount,
                'raw_description'        => $rawDesc,
                'translated_description' => $ruleResult['translated_description'],
                'installment_current'    => null,
                'installment_total'      => null,
                'month_year'             => $monthYear,
            ];
        }

        return $rows;
    }

    /**
     * Extrai o valor creditado num bloco de estorno.
     *
     * Prioridade: linha com −R$ / -R$; depois texto "de valor R$ …".
     */
    private function parseRefundAmount(string $block): ?float
    {
        if (preg_match('/[−\-]\s*R\$[\s\x{00A0}]*([\d,.]+)/u', $block, $m)) {
            return $this->parseAmount($m[1]);
        }

        if (preg_match('/de\s+valor\s+R\$[\s\x{00A0}]*([\d,.]+)/iu', $block, $m)) {
            return $this->parseAmount($m[1]);
        }

        return null;
    }

    /**
     * Linhas de desconto por antecipação de parcela (crédito na fatura).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseEarlyPaymentDiscounts(string $text): array
    {
        $matches = [];
        preg_match_all(self::DESCONTO_ANTECIPACAO_REGEX, $text, $matches, PREG_SET_ORDER);

        $rows = [];
        $year = $this->year !== 0 ? $this->year : (int) date('Y');

        foreach ($matches as $match) {
            $rawDate  = $match[1];
            $merchant = trim($match[2]);
            $amount   = $this->parseAmount($match[3]);

            if ($amount <= 0.0) {
                continue;
            }

            $rawDesc    = 'Desconto Antecipação ' . $merchant;
            $date       = $this->formatDate($rawDate, $year);
            $monthYear  = substr($date, 0, 7);
            $ruleResult = $this->ruleEngine->applyRules($merchant);

            $rows[] = [
                'category_id'            => $ruleResult['category_id'],
                'type'                   => 'entrada',
                'date'                   => $date,
                'origin'                 => 'Nubank',
                'operation'              => 'Desconto Antecipação',
                'amount'                 => $amount,
                'raw_description'        => $rawDesc,
                'translated_description' => $ruleResult['translated_description'],
                'installment_current'    => null,
                'installment_total'      => null,
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

    /**
     * Normaliza texto extraído do PDF antes do match da regex.
     *
     * PDFs Nubank costumam vir como bloco contínuo (sem quebras de linha por transação),
     * com máscaras do cartão em variantes Unicode e espaços irregulares.
     */
    private function normalizeExtractedText(string $text): string
    {
        $text = str_replace(["\x{00A0}", "\r", "\f", "\t"], [' ', '', ' ', ' '], $text);

        $text = $this->extractTransactionSection($text);

        // Colapsa espaços horizontais (preserva \n).
        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;

        // Unifica máscaras do cartão (preserva espaço antes dos 4 dígitos).
        $text = preg_replace(
            '/(?:[•·●∙]\s*){4}\s*|(?:\*{4})\s*|(?:\.{4})\s*/u',
            '•••• ',
            $text
        ) ?? $text;

        // PDF sem máscara: dígitos colados ("1470Ifd") ou com espaço ("1470 Ifd"); ignora anos 19xx/20xx.
        $text = preg_replace(
            '/(\d{2}\s+[A-Za-z]{3})\s+(?!(?:••••|\*{4}))(\d{4})(?=[A-Za-z*])/u',
            '$1 •••• $2',
            $text
        ) ?? $text;
        $text = preg_replace(
            '/(\d{2}\s+[A-Za-z]{3})\s+(?!(?:••••|\*{4}))((?!(?:19|20)\d{2})\d{4})\s+(?=[A-Za-z*])/u',
            '$1 •••• $2 ',
            $text
        ) ?? $text;

        // Cada transação numa linha própria (crítico quando o PDF vem numa linha só).
        $text = preg_replace(
            '/(?<!\n)(\d{2}\s+[A-Za-z]{3}\s+(?:••••|\*{4})\s+\d{4}\s*)/u',
            "\n$1",
            $text
        ) ?? $text;

        return trim($text);
    }

    /**
     * Mês final do período de compras (ex.: "28 DEZ" → DEZ).
     */
    private static function parsePeriodEndMonth(string $text): ?string
    {
        if (preg_match(
            '/TRANSA[ÇC][ÕO]ES\s+DE\s+\d{2}\s+[A-Za-z]{3}\s+A\s+\d{2}\s+([A-Za-z]{3})/iu',
            $text,
            $m
        )) {
            return strtoupper($m[1]);
        }

        if (preg_match(
            '/Per[íi]odo\s+vigente:.*?\s+a\s+\d{2}\s+([A-Za-z]{3})/iu',
            $text,
            $m
        )) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /**
     * Recorta a secção de transações da fatura (evita falsos positivos em cabeçalhos/datas).
     */
    private function extractTransactionSection(string $text): string
    {
        if (preg_match(
            '/TRANSA[ÇC][ÕO]ES\s+DE\s+\d{2}\s+[A-Za-z]{3}\s+A\s+\d{2}\s+[A-Za-z]{3}/iu',
            $text,
            $m,
            PREG_OFFSET_CAPTURE
        )) {
            $text = substr($text, $m[0][1]);
        }

        if (preg_match('/Pagamentos e Financiamentos/i', $text, $m, PREG_OFFSET_CAPTURE)) {
            $text = substr($text, 0, $m[0][1]);
        }

        return $text;
    }

    /**
     * Linhas na secção de transações que não são compras no cartão.
     */
    private function isNonChargeLine(string $rawDesc): bool
    {
        return (bool) preg_match(
            '/^(?:Pagamento em|Saldo restante|Saldo em aberto)/iu',
            $rawDesc
        );
    }

    /**
     * Conta linhas de compra reconhecidas no texto normalizado.
     */
    private function countChargeMatches(string $text): int
    {
        $normalized = $this->normalizeExtractedText($text);
        $matches    = [];
        preg_match_all(self::TRANSACTION_REGEX, $normalized, $matches, PREG_SET_ORDER);

        return count($matches);
    }
}

<?php

declare(strict_types=1);

/**
 * Parser de extratos do Mercado Pago em formato CSV.
 *
 * O CSV exportado pelo Mercado Pago contém linhas de resumo no topo antes das
 * transações reais. Este parser salta essas linhas e começa a processar apenas
 * quando encontra uma linha cuja coluna 0 seja uma data válida (d-m-Y).
 *
 * Formato das colunas nas linhas de transação:
 *   Coluna 0 — Data        (d-m-Y, ex: "05-04-2026")
 *   Coluna 1 — Operação    (ex: "Pix enviado João Silva")
 *   Coluna 2 — external_id (ex: "12345678")
 *   Coluna 3 — Valor       (ex: "-45,90" ou "1200,00")
 *
 * Uso:
 *   $parser = new MercadoPagoParser($ruleEngine);
 *   $rows   = $parser->parse('/path/to/extrato.csv');
 */
final class MercadoPagoParser
{
    /**
     * Prefixos removidos da descrição bruta antes de passar ao RuleEngine.
     * Usa str_ireplace para ser insensível a maiúsculas/minúsculas.
     *
     * @var list<string>
     */
    private const DESCRIPTION_PREFIXES = [
        // Mais específicos primeiro (str_ireplace percorre a lista nesta ordem)
        'Transferência Pix recebida ',
        'Transferência Pix enviada ',
        'Pagamento com QR Pix ',
        'Dinheiro reservado ',
        'Pagamento de contas ',
        'Dinheiro retirado ',
        'Pix enviado ',
        'Pix recebido ',
        'Pagamento ',
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
     * @return array<int, array<string, mixed>>
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
     * Lê o handle CSV, salta linhas de cabeçalho/resumo e processa as transações.
     *
     * O início das transações é detectado pela presença de uma data válida (d-m-Y)
     * na coluna 0 — não por número de linha fixo, tornando o parser robusto a
     * variações no número de linhas de cabeçalho do Mercado Pago.
     *
     * @param  resource $handle
     * @return array<int, array<string, mixed>>
     */
    private function readCsv($handle): array
    {
        $rows = [];

        while (($row = fgetcsv($handle, 1000, ';', '"', '')) !== false) {
            if (count($row) < 4) {
                continue;
            }

            // Salta linhas até encontrar uma com data válida na coluna 0
            $dateObj = \DateTime::createFromFormat('d-m-Y', trim($row[0]));

            if ($dateObj === false) {
                continue;
            }

            $record = $this->processRow($row, $dateObj);

            if ($record !== null) {
                $rows[] = $record;
            }
        }

        return $rows;
    }

    /**
     * Processa uma linha já validada e retorna o registo formatado, ou null se inválida.
     *
     * @param  list<string>   $row
     * @param  \DateTime      $dateObj Data já parseada da coluna 0.
     * @return array<string, mixed>|null
     */
    private function processRow(array $row, \DateTime $dateObj): ?array
    {
        $date      = $dateObj->format('Y-m-d');
        $monthYear = $dateObj->format('Y-m');

        // --- Valor (coluna 3) ---
        $amount = (float) str_replace(',', '.', trim($row[3]));

        if ($amount === 0.0) {
            return null;
        }

        // --- Apenas transações Pix são importadas por agora ---
        $rawOperation = trim($row[1]);

        if (stripos($rawOperation, 'pix') === false) {
            return null;
        }

        if ($amount < 0.0) {
            $type   = 'saída';
            $amount = abs($amount);
        } else {
            $type = 'entrada';
        }

        // --- Descrição limpa (coluna 1 sem prefixos) ---
        $rawDescription = trim(str_ireplace(self::DESCRIPTION_PREFIXES, '', $rawOperation));

        if ($rawDescription === '') {
            $rawDescription = $rawOperation;
        }

        // --- external_id (coluna 2) ---
        $externalId = trim($row[2]);
        $externalId = $externalId !== '' ? $externalId : null;

        $ruleResult = $this->ruleEngine->applyRules($rawDescription);

        return [
            'category_id'            => $ruleResult['category_id'],
            'type'                   => $type,
            'date'                   => $date,
            'origin'                 => 'MercadoPago',
            'operation'              => 'Pix',
            'amount'                 => $amount,
            'raw_description'        => $rawDescription,
            'translated_description' => $ruleResult['translated_description'],
            'installment_current'    => null,
            'installment_total'      => null,
            'month_year'             => $monthYear,
            'external_id'            => $externalId,
        ];
    }
}

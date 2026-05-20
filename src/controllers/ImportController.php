<?php

declare(strict_types=1);

/**
 * Controlador de importação de ficheiros financeiros.
 *
 * Recebe um upload HTTP (PDF ou CSV), detecta a fonte (Nubank/MercadoPago),
 * delega ao parser correto e persiste as transações na base de dados numa
 * única transação PDO.
 *
 * Uso via HTTP:
 *   POST /import.php
 *   Content-Type: multipart/form-data
 *   Campo: file (PDF ou CSV)
 */
final class ImportController
{
    /**
     * MIME types mapeados para o parser correspondente.
     *
     * @var array<string, string>
     */
    private const MIME_MAP = [
        'application/pdf'            => 'nubank',
        'text/csv'                   => 'mercadopago',
        'text/plain'                 => 'mercadopago',
        'application/vnd.ms-excel'   => 'mercadopago',
        'application/octet-stream'   => 'mercadopago',
    ];

    /**
     * @param PDO        $pdo        Conexão PDO para persistência.
     * @param RuleEngine $ruleEngine Motor de regras injetado nos parsers.
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly RuleEngine $ruleEngine,
        private readonly ?InternalTransferService $internalTransfers = null,
    ) {
    }

    /**
     * Ponto de entrada para uploads HTTP via $_FILES['file'].
     *
     * @return array{success: bool, imported: int, skipped: int, month_year_groups: array<string, int>, error?: string}
     */
    public function handleUpload(): array
    {
        if (
            !isset($_FILES['file']) ||
            $_FILES['file']['error'] !== UPLOAD_ERR_OK
        ) {
            $errorCode = $_FILES['file']['error'] ?? -1;

            return [
                'success'           => false,
                'imported'          => 0,
                'skipped'           => 0,
                'month_year_groups' => [],
                'error'             => $this->uploadErrorMessage($errorCode),
            ];
        }

        $tmpPath      = $_FILES['file']['tmp_name'];
        $originalName = $_FILES['file']['name'] ?? '';
        $mimeType     = $_FILES['file']['type'] ?? '';

        // finfo é mais fiável do que o MIME declarado pelo cliente
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($tmpPath);

        $parserType = $this->detectParser($detectedMime ?: $mimeType, $originalName);

        if ($parserType === null) {
            return [
                'success'           => false,
                'imported'          => 0,
                'skipped'           => 0,
                'month_year_groups' => [],
                'error'             => "Tipo de ficheiro não suportado: '{$detectedMime}'.",
            ];
        }

        try {
            $rows = $this->runParser($parserType, $tmpPath, $originalName);
        } catch (\RuntimeException $e) {
            return [
                'success'           => false,
                'imported'          => 0,
                'skipped'           => 0,
                'month_year_groups' => [],
                'error'             => $e->getMessage(),
            ];
        }

        $profileName = trim((string) ($_POST['profile_name'] ?? ''));

        return $this->persistTransactions($rows, $profileName);
    }

    // ---------------------------------------------------------------------------
    // Métodos privados
    // ---------------------------------------------------------------------------

    /**
     * Detecta o tipo de parser com base no MIME type; usa a extensão como fallback.
     *
     * @return 'nubank'|'mercadopago'|null
     */
    private function detectParser(string $mimeType, string $originalName): ?string
    {
        if (isset(self::MIME_MAP[$mimeType])) {
            return self::MIME_MAP[$mimeType];
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf'  => 'nubank',
            'csv'  => 'mercadopago',
            default => null,
        };
    }

    /**
     * Instancia e executa o parser correto.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException Propagada do parser.
     */
    private function runParser(string $parserType, string $filePath, string $originalName = ''): array
    {
        if ($parserType === 'nubank') {
            $parser = new NubankParser($this->ruleEngine);

            return $parser->parse($filePath, $originalName);
        }

        $parser = new MercadoPagoParser($this->ruleEngine);

        return $parser->parse($filePath);
    }

    /**
     * Persiste um lote de transações já formatadas na DB.
     *
     * Método público para uso externo (ex: import_text.php).
     * Linhas com `amount === 0.0` ou `date` vazia são contabilizadas em
     * `$skipped` sem abortar o batch.
     *
     * @param  array<int, array<string, mixed>> $rows
     * @return array{imported: int, skipped: int, month_year_groups: array<string, int>}
     */
    public function persistFromRows(array $rows, string $profileName = ''): array
    {
        return $this->persistTransactions($rows, $profileName);
    }

    /**
     * Persiste um lote de transações na DB numa única transação PDO.
     *
     * @param  array<int, array<string, mixed>> $rows
     * @return array{imported: int, skipped: int, month_year_groups: array<string, int>}
     */
    private function persistTransactions(array $rows, string $profileName = ''): array
    {
        $rows = $this->applyInternalTransferRules($rows, $profileName);

        // INSERT OR IGNORE descarta silenciosamente duplicados via:
        //   - external_id UNIQUE (MercadoPago)
        //   - idx_uniq_transaction (date+origin+operation+raw_description+amount)
        $sql = <<<'SQL'
            INSERT OR IGNORE INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description,
                 translated_description, installment_current, installment_total, month_year,
                 external_id)
            VALUES
                (:category_id, :type, :date, :origin, :operation, :amount, :raw_description,
                 :translated_description, :installment_current, :installment_total, :month_year,
                 :external_id)
            SQL;

        $stmt            = $this->pdo->prepare($sql);
        $imported        = 0;
        $skipped         = 0;
        $monthYearGroups = [];

        $this->pdo->beginTransaction();

        try {
            foreach ($rows as $row) {
                if (empty($row['date']) || ($row['amount'] ?? 0.0) === 0.0) {
                    $skipped++;
                    continue;
                }

                $stmt->execute([
                    ':category_id'            => $row['category_id'],
                    ':type'                   => $row['type'],
                    ':date'                   => $row['date'],
                    ':origin'                 => $row['origin'],
                    ':operation'              => $row['operation'],
                    ':amount'                 => $row['amount'],
                    ':raw_description'        => $row['raw_description'],
                    ':translated_description' => $row['translated_description'] ?? null,
                    ':installment_current'    => $row['installment_current'] ?? null,
                    ':installment_total'      => $row['installment_total'] ?? null,
                    ':month_year'             => $row['month_year'],
                    ':external_id'            => $row['external_id'] ?? null,
                ]);

                if ($stmt->rowCount() > 0) {
                    $imported++;
                    $my = $row['month_year'];
                    $monthYearGroups[$my] = ($monthYearGroups[$my] ?? 0) + 1;
                } else {
                    $skipped++;
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'success'           => true,
            'imported'          => $imported,
            'skipped'           => $skipped,
            'month_year_groups' => $monthYearGroups,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyInternalTransferRules(array $rows, string $profileName): array
    {
        if (trim($profileName) === '') {
            return $rows;
        }

        $service = $this->internalTransfers ?? new InternalTransferService($this->pdo);

        return $service->applyToRows($rows, $profileName);
    }

    /**
     * Traduz códigos de erro de upload para mensagens legíveis.
     */
    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O ficheiro excede o tamanho máximo permitido.',
            UPLOAD_ERR_PARTIAL                        => 'O ficheiro foi enviado parcialmente.',
            UPLOAD_ERR_NO_FILE                        => 'Nenhum ficheiro foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR                     => 'Pasta temporária não disponível.',
            UPLOAD_ERR_CANT_WRITE                     => 'Falha ao gravar o ficheiro no disco.',
            default                                   => 'Erro desconhecido no upload.',
        };
    }
}

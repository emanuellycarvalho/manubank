<?php

declare(strict_types=1);

/**
 * Diagnóstico: extrai texto de um PDF Nubank e mostra matches da regex.
 *
 * Uso:
 *   php scripts/debug_nubank_pdf.php /caminho/para/fatura.pdf
 */

require_once __DIR__ . '/../vendor/autoload.php';

$pdfPath = $argv[1] ?? __DIR__ . '/../tests/fixtures/fatura_nubank.pdf';

if (!is_readable($pdfPath)) {
    fwrite(STDERR, "PDF não encontrado: {$pdfPath}\n");
    fwrite(STDERR, "Coloque o ficheiro em tests/fixtures/fatura_nubank.pdf ou passe o caminho como argumento.\n");
    exit(1);
}

$parser = new \Smalot\PdfParser\Parser();
$pdf    = $parser->parseFile($pdfPath);
$raw    = $pdf->getText();

echo "=== PDF: {$pdfPath} ===\n";
echo 'Tamanho texto bruto: ' . strlen($raw) . " bytes\n\n";

echo "--- TEXTO BRUTO (primeiros 4000 chars) ---\n";
echo substr($raw, 0, 4000) . "\n\n";

$text = $raw;
echo "--- TEXTO BRUTO (secção transações, se existir) ---\n";
if (preg_match('/TRANSA[ÇC][ÕO]ES DE.+$/ius', $raw, $sec)) {
    echo substr($sec[0], 0, 4000) . "\n\n";
}

$regex = '/(?m)^(\d{2}\s[a-zA-Z]{3})\s+(?:••••|\*{4})\s+(\d{4})\s*(.+?)(?:\s+-\s+Parcela\s+(\d+)\/(\d+))?\s+R\$\s*([\d,.]+)/u';
$matches = [];
preg_match_all($regex, $text, $matches, PREG_SET_ORDER);

echo 'Matches regex estrita: ' . count($matches) . "\n\n";

foreach ($matches as $i => $m) {
    printf(
        "%3d) %s | %s | R$ %s | parcela %s/%s\n",
        $i + 1,
        $m[1],
        trim($m[3]),
        $m[6],
        $m[4] ?? '-',
        $m[5] ?? '-'
    );
}

// Linhas que parecem transação mas não deram match
echo "\n--- LINHAS SUSPEITAS (DD MMM + 4 dígitos) sem match ---\n";
$lines = preg_split('/\n/', $text) ?: [];
$suspect = 0;
foreach ($lines as $line) {
    if (preg_match('/\d{2}\s+[A-Za-z]{3}/', $line) && !preg_match($regex, $line)) {
        if (strlen(trim($line)) > 10) {
            echo trim($line) . "\n";
            $suspect++;
            if ($suspect >= 30) {
                echo "... (truncado)\n";
                break;
            }
        }
    }
}

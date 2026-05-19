<?php

declare(strict_types=1);

/**
 * Endpoint de cola de texto para importação manual de transações Nubank.
 *
 * Alternativa ao upload de PDF para casos em que o parser PDF falha ou
 * o utilizador prefere colar o texto diretamente da fatura.
 *
 * POST /import_text.php
 * Content-Type: application/json
 * Body:
 * {
 *   "text": "29 MAR •••• 1470 Mercadolivre*Mercadol - Parcela 6/12 R$ 286,58\n02 ABR ...",
 *   "year": 2026  (opcional; omitir para usar o ano corrente)
 * }
 *
 * Resposta de sucesso (HTTP 200):
 * {
 *   "success": true,
 *   "imported": 5,
 *   "skipped": 0,
 *   "month_year_groups": {"2026-03": 1, "2026-04": 4},
 *   "preview": [ ... ]  (primeiros 3 registos importados, para confirmação visual)
 * }
 */

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Emite uma resposta JSON e encerra o script.
 *
 * @param mixed $data
 */
function jsonResponse(mixed $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Apenas POST é suportado.'], 405);
}

try {
    $body = json_decode(file_get_contents('php://input') ?: '{}', true);

    if (!is_array($body) || empty(trim($body['text'] ?? ''))) {
        jsonResponse([
            'success' => false,
            'error'   => "Campo 'text' obrigatório: cole o texto copiado da fatura Nubank.",
        ], 422);
    }

    $text = (string) $body['text'];
    $year = isset($body['year']) ? (int) $body['year'] : 0;

    $pdo        = Database::getConnection();
    $ruleEngine = new RuleEngine($pdo);
    $parser     = new NubankParser($ruleEngine, $year);

    $rows = $parser->parseText($text);

    if (empty($rows)) {
        jsonResponse([
            'success'  => false,
            'imported' => 0,
            'skipped'  => 0,
            'error'    => 'Nenhuma transação reconhecida no texto. Verifique o formato: "DD MMM •••• DDDD Descrição R$ valor".',
        ], 422);
    }

    // Persistir usando o mesmo método do ImportController
    $controller = new ImportController($pdo, $ruleEngine);
    $result     = $controller->persistFromRows($rows);

    jsonResponse([
        'success'           => true,
        'imported'          => $result['imported'],
        'skipped'           => $result['skipped'],
        'month_year_groups' => $result['month_year_groups'],
        'preview'           => array_slice($rows, 0, 3),
    ]);
} catch (\Throwable $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}

<?php

declare(strict_types=1);

/**
 * Taxa CDI anualizada (cache 24 h).
 *
 * GET /api_cdi.php
 * GET /api_cdi.php?refresh=1  → ignora cache e busca nova cotação
 */

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * @param mixed $data
 */
function jsonResponse(mixed $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['success' => false, 'error' => 'Método não suportado.'], 405);
    }

    $forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] !== '0';
    $service      = new CdiService();

    $fromCache = !$forceRefresh ? $service->getCachedMetadata() : null;

    if ($fromCache !== null) {
        jsonResponse([
            'success' => true,
            'data'    => [
                'cdi_annual_rate' => $fromCache['rate'],
                'fetched_at'      => $fromCache['fetched_at'],
                'source'          => $fromCache['source'],
                'from_cache'      => true,
            ],
        ]);
    }

    $rate = $service->getAnnualCdiRate($forceRefresh);
    $meta = $service->getCachedMetadata();

    jsonResponse([
        'success' => true,
        'data'    => [
            'cdi_annual_rate' => $rate,
            'fetched_at'      => $meta['fetched_at'] ?? null,
            'source'          => $meta['source'] ?? 'live',
            'from_cache'      => false,
        ],
    ]);
} catch (\Throwable $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}

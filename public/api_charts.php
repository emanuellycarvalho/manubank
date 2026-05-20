<?php

declare(strict_types=1);

/**
 * Endpoint HTTP para agregação de dados de gráficos.
 *
 * GET /api_charts.php?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&granularity=day|week|month|semester
 *     → séries agregadas (receitas, despesas, rendimentos) por period_label
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

    $startDate   = trim($_GET['start_date'] ?? '');
    $endDate     = trim($_GET['end_date'] ?? '');
    $granularity = trim($_GET['granularity'] ?? '');

    if ($startDate === '' || $endDate === '' || $granularity === '') {
        jsonResponse([
            'success' => false,
            'error'   => "Parâmetros obrigatórios: start_date, end_date, granularity.",
        ], 422);
    }

    $pdo        = Database::getConnection();
    $controller = new ChartController($pdo);
    $data       = $controller->getAggregatedSeries($startDate, $endDate, $granularity);

    jsonResponse(['success' => true, 'data' => $data]);
} catch (InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}

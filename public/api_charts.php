<?php

declare(strict_types=1);

/**
 * Endpoint HTTP para agregação de dados de gráficos.
 *
 * GET /api_charts.php?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&granularity=day|week|month|semester
 *     → séries agregadas (receitas, despesas, rendimentos) por period_label
 *
 * GET /api_charts.php?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&breakdown=category
 *     → despesas agrupadas por categoria no período
 *
 * GET /api_charts.php?start_date=...&end_date=...&granularity=...&breakdown=fixed_variable
 *     → despesas Fixo vs. Variável por period_label
 *
 * GET /api_charts.php?start_date=...&end_date=...&granularity=...&breakdown=category_evolution&category_id=N
 *     → evolução de despesas de uma categoria por period_label
 *
 * GET /api_charts.php?start_date=...&end_date=...&granularity=...&breakdown=yield_growth
 *     → rendimento_mensal e rendimento_acumulado por período (type = rendimento)
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

    $startDate = trim($_GET['start_date'] ?? '');
    $endDate   = trim($_GET['end_date'] ?? '');
    $breakdown = strtolower(trim($_GET['breakdown'] ?? ''));

    if ($startDate === '' || $endDate === '') {
        jsonResponse([
            'success' => false,
            'error'   => "Parâmetros obrigatórios: start_date, end_date.",
        ], 422);
    }

    $pdo        = Database::getConnection();
    $controller = new ChartController($pdo);

    $granularity = trim($_GET['granularity'] ?? '');

    if ($breakdown === 'category') {
        $data = $controller->getExpensesByCategory($startDate, $endDate);
        jsonResponse(['success' => true, 'data' => $data]);
    }

    if ($granularity === '') {
        jsonResponse([
            'success' => false,
            'error'   => "Parâmetro 'granularity' obrigatório (exceto breakdown=category).",
        ], 422);
    }

    if ($breakdown === 'fixed_variable') {
        $data = $controller->getFixedVsVariableSeries($startDate, $endDate, $granularity);
        jsonResponse(['success' => true, 'data' => $data]);
    }

    if ($breakdown === 'category_evolution') {
        $categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
        $data       = $controller->getCategoryEvolutionSeries(
            $startDate,
            $endDate,
            $granularity,
            $categoryId,
        );
        jsonResponse(['success' => true, 'data' => $data]);
    }

    if ($breakdown === 'yield_growth') {
        $data = $controller->getYieldGrowthSeries($startDate, $endDate, $granularity);
        jsonResponse(['success' => true, 'data' => $data]);
    }

    $data = $controller->getAggregatedSeries($startDate, $endDate, $granularity);
    jsonResponse(['success' => true, 'data' => $data]);
} catch (InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}

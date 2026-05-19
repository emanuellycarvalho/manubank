<?php

declare(strict_types=1);

/**
 * Endpoint HTTP para fechamentos mensais.
 *
 * GET  /api_closures.php?month_year=YYYY-MM
 *      → resumo do mês (rollover + receitas - despesas efectivas)
 *
 * GET  /api_closures.php?month_year=YYYY-MM&saved=1
 *      → fechamento já gravado, com alocações
 *
 * POST /api_closures.php
 *      body: {"month_year":"2026-04","investments":[...],"rollover_override":null}
 *      → grava o fechamento e os aportes
 */

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

/**
 * Valida o formato YYYY-MM.
 */
function isValidMonthYear(string $value): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}$/', $value);
}

try {
    $pdo          = Database::getConnection();
    $reimbursements = new ReimbursementController($pdo);
    $controller   = new ClosureController($pdo, $reimbursements);
    $method       = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $monthYear = trim($_GET['month_year'] ?? '');

        if (!isValidMonthYear($monthYear)) {
            jsonResponse(['success' => false, 'error' => "Parâmetro 'month_year' obrigatório no formato YYYY-MM."], 422);
        }

        if (isset($_GET['saved'])) {
            $closure = $controller->getClosure($monthYear);

            if ($closure === null) {
                jsonResponse(['success' => false, 'error' => "Nenhum fechamento encontrado para {$monthYear}."], 404);
            }

            jsonResponse(['success' => true, 'data' => $closure]);
        }

        $rolloverOverride = isset($_GET['rollover'])
            ? (float) $_GET['rollover']
            : null;

        jsonResponse(['success' => true, 'data' => $controller->getMonthlySummary($monthYear, $rolloverOverride)]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input') ?: '{}', true);

        if (!is_array($body)) {
            jsonResponse(['success' => false, 'error' => 'JSON inválido no corpo da requisição.'], 422);
        }

        $monthYear = $body['month_year'] ?? '';

        if (!isValidMonthYear((string) $monthYear)) {
            jsonResponse(['success' => false, 'error' => "Campo 'month_year' obrigatório no formato YYYY-MM."], 422);
        }

        if (!isset($body['investments']) || !is_array($body['investments'])) {
            jsonResponse(['success' => false, 'error' => "Campo 'investments' obrigatório (array)."], 422);
        }

        $rolloverOverride = isset($body['rollover_override'])
            ? (float) $body['rollover_override']
            : null;

        $closureId = $controller->saveClosure(
            (string) $monthYear,
            $body['investments'],
            $rolloverOverride
        );

        jsonResponse(['success' => true, 'closure_id' => $closureId], 201);
    }

    jsonResponse(['success' => false, 'error' => 'Método não suportado.'], 405);
} catch (\InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
} catch (\Throwable $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}

<?php

declare(strict_types=1);

/**
 * Endpoint HTTP para gestão de reembolsos.
 *
 * GET  /api_reimbursements.php
 *      → lista claims activos (Aberto ou Parcial)
 *
 * POST /api_reimbursements.php
 *      body: {"action":"create_claim","transaction_id":1,"expected_amount":50.00,"description":"..."}
 *      → cria um novo claim
 *
 * POST /api_reimbursements.php
 *      body: {"action":"register_payment","income_transaction_id":5,"allocations":[{"claim_id":1,"paid_amount":50.00}]}
 *      → regista pagamentos e actualiza status dos claims
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

try {
    $pdo        = Database::getConnection();
    $controller = new ReimbursementController($pdo);
    $method     = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        jsonResponse(['success' => true, 'data' => $controller->getActiveClaims()]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input') ?: '{}', true);

        if (!is_array($body)) {
            jsonResponse(['success' => false, 'error' => 'JSON inválido no corpo da requisição.'], 422);
        }

        $action = $body['action'] ?? '';

        if ($action === 'create_claim') {
            $required = ['transaction_id', 'expected_amount', 'description'];

            foreach ($required as $field) {
                if (!isset($body[$field])) {
                    jsonResponse(['success' => false, 'error' => "Campo obrigatório em falta: '{$field}'."], 422);
                }
            }

            $claimId = $controller->createClaim(
                (int)   $body['transaction_id'],
                (float) $body['expected_amount'],
                (string) $body['description']
            );

            jsonResponse(['success' => true, 'claim_id' => $claimId], 201);
        }

        if ($action === 'register_payment') {
            if (!isset($body['income_transaction_id'], $body['allocations'])) {
                jsonResponse(['success' => false, 'error' => "Campos 'income_transaction_id' e 'allocations' são obrigatórios."], 422);
            }

            if (!is_array($body['allocations']) || empty($body['allocations'])) {
                jsonResponse(['success' => false, 'error' => "'allocations' deve ser um array não vazio."], 422);
            }

            $controller->registerPayment(
                (int) $body['income_transaction_id'],
                $body['allocations']
            );

            jsonResponse(['success' => true, 'message' => 'Pagamentos registados com sucesso.']);
        }

        jsonResponse(['success' => false, 'error' => "Acção desconhecida: '{$action}'."], 422);
    }

    jsonResponse(['success' => false, 'error' => 'Método não suportado.'], 405);
} catch (\InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
} catch (\Throwable $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}

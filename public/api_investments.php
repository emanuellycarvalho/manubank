<?php

declare(strict_types=1);

/**
 * Endpoint HTTP para gestão de investimentos (objetivos e aportes).
 *
 * GET    /api_investments.php
 *        → lista objetivos com métricas e histórico
 *
 * POST   /api_investments.php
 *        body: {"action":"create_objective","name":"...","target_amount":10000,"end_date":"2028-12-31"}
 *        body: {"action":"update_objective","objective_id":1,"name":"...","target_amount":10000,"end_date":"2028-12-31"}
 *        body: {"action":"add_entry","objective_id":1,"type":"entrada","amount":500,"date":"2026-05-20","description":"..."}
 *        body: {"action":"update_entry","entry_id":1,"type":"entrada","amount":500,"date":"2026-05-20","description":"..."}
 *
 * DELETE /api_investments.php?entry_id=3
 *        → remove um lançamento
 *
 * DELETE /api_investments.php?objective_id=1
 *        → remove um objetivo (e lançamentos em CASCADE)
 */

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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
 * Valida o formato YYYY-MM-DD.
 */
function isValidDate(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    if ($date === false) {
        return false;
    }

    $errors = DateTime::getLastErrors();

    return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
}

try {
    $pdo        = Database::getConnection();
    $controller = new InvestmentController($pdo);
    $method     = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        jsonResponse(['success' => true, 'data' => $controller->getObjectivesWithMetrics()]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input') ?: '{}', true);

        if (!is_array($body)) {
            jsonResponse(['success' => false, 'error' => 'JSON inválido no corpo da requisição.'], 422);
        }

        $action = $body['action'] ?? '';

        if ($action === 'create_objective') {
            $required = ['name', 'target_amount', 'end_date'];

            foreach ($required as $field) {
                if (!isset($body[$field]) || (is_string($body[$field]) && trim((string) $body[$field]) === '')) {
                    jsonResponse(['success' => false, 'error' => "Campo obrigatório em falta: '{$field}'."], 422);
                }
            }

            $endDate = (string) $body['end_date'];

            if (!isValidDate($endDate)) {
                jsonResponse(['success' => false, 'error' => "Campo 'end_date' deve estar no formato YYYY-MM-DD."], 422);
            }

            $objectiveId = $controller->createObjective(
                (string) $body['name'],
                (float) $body['target_amount'],
                $endDate
            );

            jsonResponse(['success' => true, 'objective_id' => $objectiveId], 201);
        }

        if ($action === 'add_entry') {
            $required = ['objective_id', 'type', 'amount', 'date'];

            foreach ($required as $field) {
                if (!isset($body[$field])) {
                    jsonResponse(['success' => false, 'error' => "Campo obrigatório em falta: '{$field}'."], 422);
                }
            }

            $date = (string) $body['date'];

            if (!isValidDate($date)) {
                jsonResponse(['success' => false, 'error' => "Campo 'date' deve estar no formato YYYY-MM-DD."], 422);
            }

            $entryId = $controller->addEntry(
                (int) $body['objective_id'],
                (string) $body['type'],
                (float) $body['amount'],
                $date,
                isset($body['description']) ? (string) $body['description'] : ''
            );

            jsonResponse(['success' => true, 'entry_id' => $entryId], 201);
        }

        if ($action === 'update_objective') {
            $required = ['objective_id', 'name', 'target_amount', 'end_date'];

            foreach ($required as $field) {
                if (!isset($body[$field]) || (is_string($body[$field]) && trim((string) $body[$field]) === '')) {
                    jsonResponse(['success' => false, 'error' => "Campo obrigatório em falta: '{$field}'."], 422);
                }
            }

            $endDate = (string) $body['end_date'];

            if (!isValidDate($endDate)) {
                jsonResponse(['success' => false, 'error' => "Campo 'end_date' deve estar no formato YYYY-MM-DD."], 422);
            }

            $controller->updateObjective(
                (int) $body['objective_id'],
                (string) $body['name'],
                (float) $body['target_amount'],
                $endDate
            );

            jsonResponse(['success' => true, 'message' => 'Objetivo atualizado com sucesso.']);
        }

        if ($action === 'update_entry') {
            $required = ['entry_id', 'type', 'amount', 'date'];

            foreach ($required as $field) {
                if (!isset($body[$field])) {
                    jsonResponse(['success' => false, 'error' => "Campo obrigatório em falta: '{$field}'."], 422);
                }
            }

            $date = (string) $body['date'];

            if (!isValidDate($date)) {
                jsonResponse(['success' => false, 'error' => "Campo 'date' deve estar no formato YYYY-MM-DD."], 422);
            }

            $controller->updateEntry(
                (int) $body['entry_id'],
                (string) $body['type'],
                (float) $body['amount'],
                $date,
                isset($body['description']) ? (string) $body['description'] : ''
            );

            jsonResponse(['success' => true, 'message' => 'Entrada actualizada com sucesso.']);
        }

        jsonResponse(['success' => false, 'error' => "Acção desconhecida: '{$action}'."], 422);
    }

    if ($method === 'DELETE') {
        if (isset($_GET['objective_id'])) {
            $objectiveId = (int) $_GET['objective_id'];

            if ($objectiveId <= 0) {
                jsonResponse(['success' => false, 'error' => "Parâmetro 'objective_id' inválido."], 422);
            }

            $controller->deleteObjective($objectiveId);

            jsonResponse(['success' => true, 'message' => 'Objetivo removido com sucesso.']);
        }

        if (!isset($_GET['entry_id'])) {
            jsonResponse(['success' => false, 'error' => "Parâmetro 'entry_id' ou 'objective_id' obrigatório."], 422);
        }

        $entryId = (int) $_GET['entry_id'];

        if ($entryId <= 0) {
            jsonResponse(['success' => false, 'error' => "Parâmetro 'entry_id' inválido."], 422);
        }

        $controller->deleteEntry($entryId);

        jsonResponse(['success' => true, 'message' => 'Entrada removida com sucesso.']);
    }

    jsonResponse(['success' => false, 'error' => 'Método não suportado.'], 405);
} catch (\InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
} catch (\Throwable $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}

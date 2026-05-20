<?php

declare(strict_types=1);

/**
 * CRUD de alocações / contas de investimento (consolidado).
 *
 * GET    /api_allocations.php       → lista (JOIN com finalidade)
 * GET    /api_allocations.php?id=N  → uma alocação
 * POST   /api_allocations.php       → cria (JSON body)
 * PUT    /api_allocations.php?id=N  → actualiza (JSON body)
 * DELETE /api_allocations.php?id=N  → remove
 */

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

/**
 * @return array<string, mixed>
 */
function parseJsonBody(): array
{
    $body = json_decode(file_get_contents('php://input') ?: '{}', true);

    if (!is_array($body)) {
        jsonResponse(['success' => false, 'error' => 'JSON inválido no corpo da requisição.'], 422);
    }

    return $body;
}

try {
    $pdo        = Database::getConnection();
    $controller = new AllocationController($pdo);
    $method     = $_SERVER['REQUEST_METHOD'];
    $id         = isset($_GET['id']) ? (int) $_GET['id'] : null;

    if ($method === 'GET') {
        if ($id !== null && $id > 0) {
            jsonResponse(['success' => true, 'data' => $controller->getById($id)]);
        }

        jsonResponse(['success' => true, 'data' => $controller->listAll()]);
    }

    if ($method === 'POST') {
        $created = $controller->create(parseJsonBody());
        jsonResponse(['success' => true, 'data' => $created], 201);
    }

    if ($method === 'PUT') {
        if ($id === null || $id <= 0) {
            jsonResponse(['success' => false, 'error' => "Parâmetro 'id' obrigatório na URL."], 422);
        }

        $updated = $controller->update($id, parseJsonBody());
        jsonResponse(['success' => true, 'data' => $updated]);
    }

    if ($method === 'DELETE') {
        if ($id === null || $id <= 0) {
            jsonResponse(['success' => false, 'error' => "Parâmetro 'id' obrigatório na URL."], 422);
        }

        $controller->delete($id);
        jsonResponse(['success' => true, 'message' => 'Alocação removida com sucesso.']);
    }

    jsonResponse(['success' => false, 'error' => 'Método não suportado.'], 405);
} catch (\InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
} catch (\Throwable $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}

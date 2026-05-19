<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/db/Database.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo    = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

/**
 * Envia uma resposta JSON e encerra o script.
 *
 * @param mixed $data
 */
function jsonResponse(mixed $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Lê e valida o corpo JSON da requisição.
 *
 * @return array<string, mixed>
 */
function parseBody(): array
{
    $raw  = file_get_contents('php://input');
    $body = json_decode((string) $raw, true);

    if (!is_array($body)) {
        jsonResponse(['error' => 'Corpo da requisição inválido.'], 400);
    }

    return $body;
}

try {
    match ($method) {

        'GET' => (function () use ($pdo, $id): void {
            if ($id !== null) {
                $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
                $stmt->execute([$id]);
                $category = $stmt->fetch();

                if ($category === false) {
                    jsonResponse(['error' => 'Categoria não encontrada.'], 404);
                }

                jsonResponse($category);
            }

            $stmt = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC');
            jsonResponse($stmt->fetchAll());
        })(),

        'POST' => (function () use ($pdo): void {
            $body  = parseBody();
            $name  = trim((string) ($body['name']  ?? ''));
            $type  = trim((string) ($body['type']  ?? ''));
            $color = trim((string) ($body['color'] ?? ''));

            $allowedTypes = ['Fixo', 'Variável', 'Neutro'];

            if ($name === '' || $type === '' || $color === '') {
                jsonResponse(['error' => 'Os campos name, type e color são obrigatórios.'], 422);
            }

            if (!in_array($type, $allowedTypes, true)) {
                jsonResponse(['error' => 'Tipo inválido. Use: Fixo, Variável ou Neutro.'], 422);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO categories (name, type, color, is_active) VALUES (?, ?, ?, 1)'
            );
            $stmt->execute([$name, $type, $color]);

            jsonResponse(['id' => (int) $pdo->lastInsertId(), 'message' => 'Categoria criada.'], 201);
        })(),

        'PUT' => (function () use ($pdo, $id): void {
            if ($id === null) {
                jsonResponse(['error' => 'ID obrigatório para atualização.'], 400);
            }

            $body  = parseBody();
            $name  = trim((string) ($body['name']  ?? ''));
            $type  = trim((string) ($body['type']  ?? ''));
            $color = trim((string) ($body['color'] ?? ''));

            $allowedTypes = ['Fixo', 'Variável', 'Neutro'];

            if ($name === '' || $type === '' || $color === '') {
                jsonResponse(['error' => 'Os campos name, type e color são obrigatórios.'], 422);
            }

            if (!in_array($type, $allowedTypes, true)) {
                jsonResponse(['error' => 'Tipo inválido. Use: Fixo, Variável ou Neutro.'], 422);
            }

            $stmt = $pdo->prepare(
                'UPDATE categories SET name = ?, type = ?, color = ? WHERE id = ?'
            );
            $stmt->execute([$name, $type, $color, $id]);

            if ($stmt->rowCount() === 0) {
                jsonResponse(['error' => 'Categoria não encontrada.'], 404);
            }

            jsonResponse(['message' => 'Categoria atualizada.']);
        })(),

        'DELETE' => (function () use ($pdo, $id): void {
            if ($id === null) {
                jsonResponse(['error' => 'ID obrigatório para desativação.'], 400);
            }

            $stmt = $pdo->prepare('UPDATE categories SET is_active = 0 WHERE id = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                jsonResponse(['error' => 'Categoria não encontrada.'], 404);
            }

            jsonResponse(['message' => 'Categoria desativada.']);
        })(),

        default => jsonResponse(['error' => 'Método não suportado.'], 405),
    };
} catch (Throwable $e) {
    jsonResponse(['error' => 'Erro interno: ' . $e->getMessage()], 500);
}

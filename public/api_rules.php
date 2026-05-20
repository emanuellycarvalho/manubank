<?php

declare(strict_types=1);

/**
 * GET  /api_rules.php                    → lista todas as regras ativas
 * POST /api_rules.php                    → cria nova regra de parsing
 *      body: { "category_id": 3, "substring": "UBER", "translated_name": "Transporte" }
 * PUT    /api_rules.php?id=5             → atualiza regra
 * DELETE /api_rules.php?id=5             → desativa regra (soft delete)
 */

require_once __DIR__ . '/../src/db/Database.php';

header('Content-Type: application/json; charset=UTF-8');
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
function jsonResponse(mixed $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
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
    $pdo    = Database::getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

    match ($method) {

        'GET' => (function () use ($pdo): void {
            $stmt = $pdo->query(
                "SELECT pr.id, pr.substring, pr.translated_name, pr.is_active,
                        pr.category_id, c.name AS category_name, c.color AS category_color
                 FROM parsing_rules pr
                 JOIN categories c ON c.id = pr.category_id
                 WHERE pr.is_active = 1
                 ORDER BY pr.id ASC"
            );
            jsonResponse($stmt->fetchAll());
        })(),

        'POST' => (function () use ($pdo): void {
            $body           = parseBody();
            $categoryId     = isset($body['category_id'])   ? (int) $body['category_id']          : 0;
            $substring      = trim((string) ($body['substring']      ?? ''));
            $translatedName = trim((string) ($body['translated_name'] ?? ''));

            if ($categoryId <= 0 || $substring === '' || $translatedName === '') {
                jsonResponse(['error' => 'Os campos category_id, substring e translated_name são obrigatórios.'], 422);
            }

            // Verifica se a categoria existe
            $check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND is_active = 1');
            $check->execute([$categoryId]);

            if ($check->fetch() === false) {
                jsonResponse(['error' => "Categoria #{$categoryId} não encontrada."], 422);
            }

            $othersId = (int) $pdo->query(
                "SELECT id FROM categories WHERE LOWER(name) = 'outros' LIMIT 1"
            )->fetchColumn();

            // Verifica duplicação de substring
            $dup = $pdo->prepare(
                'SELECT id, category_id, translated_name
                 FROM parsing_rules
                 WHERE LOWER(substring) = LOWER(?) AND is_active = 1
                 LIMIT 1'
            );
            $dup->execute([$substring]);
            $existing = $dup->fetch();

            if ($existing !== false) {
                // Regra já existe — aplica retroativamente com os dados da regra existente
                // (usa os valores enviados pelo utilizador se quiser sobrescrever a categoria)
                $applyCategory    = $categoryId;
                $applyTranslation = $translatedName;
                $existingRuleId   = (int) $existing['id'];

                $updated = 0;
                if ($othersId > 0) {
                    $upd = $pdo->prepare(
                        "UPDATE transactions
                         SET category_id            = ?,
                             translated_description = ?
                         WHERE category_id = ?
                           AND LOWER(raw_description) LIKE LOWER(?)"
                    );
                    $upd->execute([$applyCategory, $applyTranslation, $othersId, '%' . $substring . '%']);
                    $updated = $upd->rowCount();
                }

                jsonResponse([
                    'id'                   => $existingRuleId,
                    'message'              => 'Regra já existia e foi aplicada retroativamente.',
                    'transactions_updated' => $updated,
                    'already_existed'      => true,
                ], 200);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO parsing_rules (category_id, substring, translated_name, is_active)
                 VALUES (?, ?, ?, 1)'
            );
            $stmt->execute([$categoryId, $substring, $translatedName]);
            $newRuleId = (int) $pdo->lastInsertId();

            // Retroage nas transações existentes que estão em "Outros" e batem com o substring
            $updated = 0;
            if ($othersId > 0) {
                $upd = $pdo->prepare(
                    "UPDATE transactions
                     SET category_id            = ?,
                         translated_description = ?
                     WHERE category_id = ?
                       AND LOWER(raw_description) LIKE LOWER(?)"
                );
                $upd->execute([$categoryId, $translatedName, $othersId, '%' . $substring . '%']);
                $updated = $upd->rowCount();
            }

            $pdo->commit();

            jsonResponse([
                'id'                   => $newRuleId,
                'message'              => 'Regra criada.',
                'transactions_updated' => $updated,
                'already_existed'      => false,
            ], 201);
        })(),

        'PUT' => (function () use ($pdo, $id): void {
            if ($id === null) {
                jsonResponse(['error' => 'ID obrigatório para atualização.'], 400);
            }

            $body           = parseBody();
            $categoryId     = isset($body['category_id']) ? (int) $body['category_id'] : 0;
            $substring      = trim((string) ($body['substring'] ?? ''));
            $translatedName = trim((string) ($body['translated_name'] ?? ''));

            if ($categoryId <= 0 || $substring === '' || $translatedName === '') {
                jsonResponse(['error' => 'Os campos category_id, substring e translated_name são obrigatórios.'], 422);
            }

            $exists = $pdo->prepare('SELECT id FROM parsing_rules WHERE id = ? AND is_active = 1');
            $exists->execute([$id]);
            if ($exists->fetch() === false) {
                jsonResponse(['error' => 'Regra não encontrada.'], 404);
            }

            $check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND is_active = 1');
            $check->execute([$categoryId]);
            if ($check->fetch() === false) {
                jsonResponse(['error' => "Categoria #{$categoryId} não encontrada."], 422);
            }

            $dup = $pdo->prepare(
                'SELECT id FROM parsing_rules
                 WHERE LOWER(substring) = LOWER(?) AND is_active = 1 AND id != ?
                 LIMIT 1'
            );
            $dup->execute([$substring, $id]);
            if ($dup->fetch() !== false) {
                jsonResponse(['error' => 'Já existe outra regra ativa com esta substring.'], 409);
            }

            $stmt = $pdo->prepare(
                'UPDATE parsing_rules
                 SET category_id = ?, substring = ?, translated_name = ?
                 WHERE id = ? AND is_active = 1'
            );
            $stmt->execute([$categoryId, $substring, $translatedName, $id]);

            jsonResponse(['message' => 'Regra atualizada.']);
        })(),

        'DELETE' => (function () use ($pdo, $id): void {
            if ($id === null) {
                jsonResponse(['error' => 'ID obrigatório.'], 400);
            }

            $stmt = $pdo->prepare('UPDATE parsing_rules SET is_active = 0 WHERE id = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                jsonResponse(['error' => 'Regra não encontrada.'], 404);
            }

            jsonResponse(['message' => 'Regra desativada.']);
        })(),

        default => jsonResponse(['error' => 'Método não suportado.'], 405),
    };
} catch (Throwable $e) {
    jsonResponse(['error' => 'Erro interno: ' . $e->getMessage()], 500);
}

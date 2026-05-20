<?php

declare(strict_types=1);

/**
 * GET  /api_transactions.php                   → todas as transações
 * GET  /api_transactions.php?month_year=MM/YYYY → filtradas por mês
 * GET  /api_transactions.php?available_months=1 → lista de meses únicos disponíveis
 * POST   /api_transactions.php                   → cria transação manual
 * PATCH /api_transactions.php?id=N              → atualiza category_id
 * DELETE /api_transactions.php?id=N            → exclui transação (e vínculos de reembolso)
 */

require_once __DIR__ . '/../src/db/Database.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'PATCH', 'DELETE'], true)) {
    http_response_code(405);
    echo json_encode(['error' => 'Método não suportado.'], JSON_UNESCAPED_UNICODE);
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
    $body = json_decode(file_get_contents('php://input') ?: '{}', true);

    if (!is_array($body)) {
        jsonResponse(['error' => 'Corpo da requisição inválido.'], 400);
    }

    return $body;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function normalizeTransactionRow(array $row): array
{
    return [
        'id'                     => (int)    $row['id'],
        'type'                   =>           $row['type'],
        'date'                   =>           $row['date'],
        'origin'                 =>           $row['origin'],
        'operation'              =>           $row['operation'],
        'amount'                 => (float)  $row['amount'],
        'raw_description'        =>           $row['raw_description'],
        'translated_description' =>           $row['translated_description'],
        'installment_current'    => $row['installment_current'] !== null ? (int) $row['installment_current'] : null,
        'installment_total'      => $row['installment_total']   !== null ? (int) $row['installment_total']   : null,
        'month_year'             =>           $row['month_year'],
        'category_id'            => (int)    $row['category_id'],
        'category_name'          =>           $row['category_name'],
        'category_color'         =>           $row['category_color'],
    ];
}

try {
    $pdo = Database::getConnection();

    // ── POST /api_transactions.php ───────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = parseBody();

        $type        = trim((string) ($body['type'] ?? ''));
        $date        = trim((string) ($body['date'] ?? ''));
        $origin      = trim((string) ($body['origin'] ?? 'Manual'));
        $operation   = trim((string) ($body['operation'] ?? 'Lançamento manual'));
        $categoryId  = isset($body['category_id']) ? (int) $body['category_id'] : 0;
        $amount      = isset($body['amount']) ? (float) $body['amount'] : 0.0;
        $rawDesc     = trim((string) ($body['raw_description'] ?? $body['description'] ?? ''));
        $translated  = trim((string) ($body['translated_description'] ?? $rawDesc));

        $allowedTypes = ['entrada', 'saída', 'rendimento'];

        if (!in_array($type, $allowedTypes, true)) {
            jsonResponse(['error' => 'Tipo inválido. Use: entrada, saída ou rendimento.'], 422);
        }

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            jsonResponse(['error' => 'Data inválida. Use o formato AAAA-MM-DD.'], 422);
        }

        if ($categoryId <= 0 || $rawDesc === '' || $translated === '') {
            jsonResponse(['error' => 'category_id, data e descrição são obrigatórios.'], 422);
        }

        if ($amount <= 0) {
            jsonResponse(['error' => 'O valor deve ser maior que zero.'], 422);
        }

        if ($origin === '') {
            $origin = 'Manual';
        }

        if ($operation === '') {
            $operation = 'Lançamento manual';
        }

        $check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND is_active = 1');
        $check->execute([$categoryId]);
        if ($check->fetch() === false) {
            jsonResponse(['error' => "Categoria #{$categoryId} não encontrada."], 422);
        }

        $monthYear = substr($date, 0, 7);

        $stmt = $pdo->prepare(
            'INSERT INTO transactions
                (category_id, type, date, origin, operation, amount, raw_description,
                 translated_description, installment_current, installment_total, month_year, external_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, NULL)'
        );
        $stmt->execute([
            $categoryId,
            $type,
            $date,
            $origin,
            $operation,
            $amount,
            $rawDesc,
            $translated,
            $monthYear,
        ]);

        $newId = (int) $pdo->lastInsertId();

        $fetch = $pdo->prepare(
            "SELECT
                t.id, t.type, t.date, t.origin, t.operation, t.amount,
                t.raw_description, t.translated_description,
                t.installment_current, t.installment_total, t.month_year,
                t.category_id, c.name AS category_name, c.color AS category_color
             FROM transactions t
             JOIN categories c ON c.id = t.category_id
             WHERE t.id = ?"
        );
        $fetch->execute([$newId]);
        $row = $fetch->fetch();

        if ($row === false) {
            jsonResponse(['error' => 'Transação criada mas não foi possível carregá-la.'], 500);
        }

        jsonResponse(normalizeTransactionRow($row), 201);
    }

    // ── DELETE /api_transactions.php?id=N ────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            jsonResponse(['error' => 'ID inválido.'], 400);
        }

        $exists = $pdo->prepare('SELECT id FROM transactions WHERE id = ?');
        $exists->execute([$id]);
        if ($exists->fetch() === false) {
            jsonResponse(['error' => 'Transação não encontrada.'], 404);
        }

        $pdo->beginTransaction();
        try {
            $claimIds = $pdo->prepare('SELECT id FROM reimbursement_claims WHERE transaction_id = ?');
            $claimIds->execute([$id]);
            $ids = $claimIds->fetchAll(PDO::FETCH_COLUMN);

            if ($ids !== []) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM reimbursement_payments WHERE claim_id IN ({$placeholders})")
                    ->execute($ids);
                $pdo->prepare("DELETE FROM reimbursement_claims WHERE id IN ({$placeholders})")
                    ->execute($ids);
            }

            $pdo->prepare('DELETE FROM reimbursement_payments WHERE income_transaction_id = ?')
                ->execute([$id]);
            $pdo->prepare('DELETE FROM transactions WHERE id = ?')->execute([$id]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        jsonResponse(['success' => true]);
    }

    // ── PATCH /api_transactions.php?id=N ─────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            jsonResponse(['error' => 'ID inválido.'], 400);
        }

        $body       = (array) json_decode(file_get_contents('php://input') ?: '{}', true);
        $categoryId = isset($body['category_id']) ? (int) $body['category_id'] : 0;

        if ($categoryId <= 0) {
            jsonResponse(['error' => 'category_id inválido.'], 422);
        }

        $check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND is_active = 1');
        $check->execute([$categoryId]);
        if ($check->fetch() === false) {
            jsonResponse(['error' => "Categoria #{$categoryId} não encontrada."], 422);
        }

        $stmt = $pdo->prepare('UPDATE transactions SET category_id = ? WHERE id = ?');
        $stmt->execute([$categoryId, $id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Transação não encontrada.'], 404);
        }

        jsonResponse(['success' => true]);
    }

    // Retorna apenas a lista de meses disponíveis (para popular o filtro)
    if (isset($_GET['available_months'])) {
        $stmt   = $pdo->query(
            "SELECT DISTINCT month_year
             FROM transactions
             ORDER BY month_year DESC"
        );
        $months = $stmt->fetchAll(PDO::FETCH_COLUMN);
        jsonResponse($months);
    }

    $monthYear = isset($_GET['month_year']) ? trim($_GET['month_year']) : null;

    $sql = "SELECT
                t.id,
                t.type,
                t.date,
                t.origin,
                t.operation,
                t.amount,
                t.raw_description,
                t.translated_description,
                t.installment_current,
                t.installment_total,
                t.month_year,
                t.category_id,
                c.name  AS category_name,
                c.color AS category_color
            FROM transactions t
            JOIN categories c ON c.id = t.category_id";

    $params = [];

    if ($monthYear !== null && $monthYear !== '') {
        $sql    .= ' WHERE t.month_year = :month_year';
        $params[':month_year'] = $monthYear;
    }

    $sql .= ' ORDER BY t.date DESC, t.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $transactions = array_map(
        static fn(array $row): array => normalizeTransactionRow($row),
        $rows
    );

    jsonResponse($transactions);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Erro interno: ' . $e->getMessage()], 500);
}

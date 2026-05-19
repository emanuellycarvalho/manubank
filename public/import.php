<?php

declare(strict_types=1);

/**
 * Ponto de entrada HTTP para importação de ficheiros financeiros.
 *
 * Aceita POST multipart/form-data com campo "file" (PDF ou CSV).
 * Retorna JSON com o resultado da importação.
 *
 * Uso:
 *   curl -X POST http://localhost:8000/import.php -F "file=@/path/to/fatura.pdf"
 *   curl -X POST http://localhost:8000/import.php -F "file=@/path/to/extrato.csv"
 */

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo        = Database::getConnection();
    $ruleEngine = new RuleEngine($pdo);
    $controller = new ImportController($pdo, $ruleEngine);
    $result     = $controller->handleUpload();

    $statusCode = ($result['success'] ?? false) ? 200 : 422;
    http_response_code($statusCode);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(
        ['success' => false, 'error' => $e->getMessage()],
        JSON_UNESCAPED_UNICODE
    );
}

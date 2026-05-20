<?php

declare(strict_types=1);

/**
 * Router do servidor PHP embutido (modo app local — um único processo).
 * APIs .php e ficheiros estáticos; resto → SPA (Vue).
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

if ($uri !== '/' && $uri !== '') {
    $file = __DIR__ . $uri;
    if (is_file($file)) {
        return false;
    }
}

if (preg_match('/\.php$/i', $uri)) {
    return false;
}

$index = __DIR__ . '/index.html';
if (!is_file($index)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Interface não compilada. Rode: make app (ou app.bat no Windows)\n";

    return true;
}

header('Content-Type: text/html; charset=utf-8');
readfile($index);

return true;

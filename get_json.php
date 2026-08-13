<?php
declare(strict_types=1);
require __DIR__ . '/login_check.php';
require __DIR__ . '/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');
try {
    echo json_encode((new Upp\Import\JsonFileReader())->read(__DIR__ . '/uploads/datoteka.json'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}

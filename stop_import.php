<?php

declare(strict_types=1);

require __DIR__ . '/login_check.php';
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/web.php';

use Upp\Import\ImportCancellation;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        upp_json_response(['success' => false, 'message' => 'Dopušten je samo POST zahtjev.'], 405);
    }
    upp_require_csrf();
    $importId = trim((string) ($_POST['import_id'] ?? ''));
    $cancellation = new ImportCancellation(__DIR__ . '/uploads/datoteka.json', $importId);
    $cancellation->request();
    upp_json_response(['success' => true, 'message' => 'Zahtjev za zaustavljanje je zaprimljen.']);
} catch (Throwable $exception) {
    upp_json_response(['success' => false, 'message' => $exception->getMessage()], 422);
}

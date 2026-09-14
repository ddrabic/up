<?php

declare(strict_types=1);

require __DIR__ . '/login_check.php';
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/web.php';

use Upp\Config\Config;
use Upp\Import\ImportCancellation;
use Upp\Import\ImportCancelledException;
use Upp\Import\ImportResult;
use Upp\Import\ImportServiceFactory;
use Upp\Logging\ImportLogger;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    upp_json_response(['success' => false, 'message' => 'Dopušten je samo POST zahtjev.'], 405);
}

$emit = static function (array $event): void {
    echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
};

$lockHandle = null;
$cancellation = null;
$streaming = false;
try {
    upp_require_csrf();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $lockPath = sys_get_temp_dir() . '/upp-import-' . hash('sha256', __DIR__ . '/uploads/datoteka.json') . '.lock';
    $lockHandle = fopen($lockPath, 'c');
    if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Import nad ovom datotekom već je pokrenut.');
    }

    set_time_limit(0);
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('X-Accel-Buffering: no');
    $streaming = true;
    $importId = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $cancellation = new ImportCancellation(__DIR__ . '/uploads/datoteka.json', $importId);
    $cancellation->clear();
    $emit(['type' => 'start', 'importId' => $importId]);
    $logger = new ImportLogger(__DIR__ . '/logs', $importId);
    $service = ImportServiceFactory::create(Config::load(__DIR__), $logger);
    $result = $service->import(
        __DIR__ . '/uploads/datoteka.json',
        static fn (ImportResult $result) => $emit(['type' => 'result'] + $result->jsonSerialize()),
        static fn (): bool => $cancellation->isRequested(),
        static function (array $progress) use ($emit, $logger): void {
            $logger->progress($progress);
            $emit(['type' => 'progress'] + $progress);
        },
    );
    $emit(['type' => 'complete', 'summary' => $result['summary']]);
} catch (ImportCancelledException $exception) {
    $emit(['type' => 'cancelled', 'processed' => $exception->processed, 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    if (!$streaming) {
        http_response_code(422);
        header('Content-Type: application/x-ndjson; charset=utf-8');
    }
    $emit(['type' => 'error', 'message' => ImportLogger::sanitize($exception->getMessage())]);
} finally {
    $cancellation?->clear();
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

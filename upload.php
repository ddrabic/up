<?php

declare(strict_types=1);

require __DIR__ . '/login_check.php';
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/web.php';

use Upp\Config\Config;
use Upp\Import\JsonFileReader;
use Upp\Import\JsonValidator;
use Upp\Import\ProductRecordNormalizer;
use Upp\Inventory\StockCalculator;

$message = '';
$success = false;
try {
    upp_require_csrf();
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        throw new RuntimeException('Datoteka nije poslana.');
    }
    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload nije uspio (kod ' . (int) $file['error'] . ').');
    }
    $config = Config::load(__DIR__);
    if ((int) ($file['size'] ?? 0) > (int) $config->get('upload_max_bytes', 52428800)) {
        throw new RuntimeException('Datoteka je veća od dopuštenog ograničenja.');
    }
    $originalName = basename((string) ($file['name'] ?? ''));
    if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'json') {
        throw new RuntimeException('Dopuštena je samo ekstenzija .json.');
    }
    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    if (!in_array($mime, ['application/json', 'text/plain', 'application/octet-stream'], true)) {
        throw new RuntimeException('Nedopušten MIME tip datoteke: ' . (string) $mime);
    }

    $records = (new JsonFileReader())->read($temporaryPath);
    (new JsonValidator())->validate($records);
    $normalizer = new ProductRecordNormalizer(new StockCalculator(), (array) $config->get('stock_included_warehouses', []));
    foreach ($records as $index => $record) {
        $normalizer->normalize($record, $index);
    }

    $destination = __DIR__ . '/uploads/datoteka.json';
    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Validiranu datoteku nije moguće spremiti.');
    }
    chmod($destination, 0640);
    $success = true;
    $message = 'Datoteka je sigurno spremljena. Broj zapisa: ' . count($records) . '.';
} catch (Throwable $exception) {
    http_response_code(422);
    $message = $exception->getMessage();
}
?>
<!doctype html><html lang="hr"><head><meta charset="utf-8"><title>Upload</title><link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css"></head>
<body><main class="w3-container"><div class="w3-panel <?= $success ? 'w3-purple' : 'w3-red' ?>"><p><?= upp_escape($message) ?></p></div>
<?php if ($success): ?><a class="w3-button" href="import_03x.php">Pokreni import</a><a class="w3-button" href="pregled_json.php">Pregledaj datoteku</a><?php else: ?><a class="w3-button" href="index.php">Natrag</a><?php endif; ?>
</main></body></html>

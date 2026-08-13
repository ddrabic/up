<?php

declare(strict_types=1);

namespace Upp\Logging;

use RuntimeException;
use Upp\Import\ImportResult;

final class ImportLogger
{
    private $handle;

    public function __construct(private readonly string $directory, private readonly string $importId)
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Nije moguće stvoriti mapu za logove.');
        }
        $path = $directory . '/import-' . $importId . '.jsonl';
        $this->handle = fopen($path, 'ab');
        if ($this->handle === false) {
            throw new RuntimeException('Nije moguće otvoriti import log.');
        }
    }

    public function result(ImportResult $result): void
    {
        fwrite($this->handle, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    }

    public function apiError(string $operation, string $endpoint, string $sku, ?int $status, ?string $wooCode, string $message, int $attempt): void
    {
        $this->write([
            'time' => gmdate(DATE_ATOM), 'operation' => $operation, 'endpoint' => $endpoint,
            'sku' => $sku, 'httpStatus' => $status, 'wooCode' => $wooCode,
            'message' => self::sanitize($message), 'attempt' => $attempt,
        ]);
    }

    public function summary(array $summary): string
    {
        $path = $this->directory . '/import-' . $this->importId . '-summary.json';
        file_put_contents($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $path;
    }

    public static function sanitize(string $message): string
    {
        $patterns = [
            '/ck_[A-Za-z0-9_]+/' => 'ck_REDACTED', '/cs_[A-Za-z0-9_]+/' => 'cs_REDACTED',
            '/(consumer_(?:key|secret)=)[^&\s]+/i' => '$1REDACTED',
            '/(Authorization:\s*(?:Bearer|Basic)\s+)[^\s]+/i' => '$1REDACTED',
        ];
        return trim((string) preg_replace(array_keys($patterns), array_values($patterns), $message));
    }

    private function write(array $entry): void
    {
        fwrite($this->handle, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}

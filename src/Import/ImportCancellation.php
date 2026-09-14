<?php

declare(strict_types=1);

namespace Upp\Import;

use RuntimeException;

final class ImportCancellation
{
    private readonly string $path;

    public function __construct(string $importFile, string $importId)
    {
        if (preg_match('/^\d{8}-\d{6}-[a-f0-9]{6}$/', $importId) !== 1) {
            throw new RuntimeException('Neispravan identifikator importa.');
        }
        $scope = hash('sha256', realpath($importFile) ?: $importFile);
        $this->path = sys_get_temp_dir() . '/upp-import-stop-' . $scope . '-' . $importId;
    }

    public function request(): void
    {
        if (file_put_contents($this->path, 'stop', LOCK_EX) === false) {
            throw new RuntimeException('Zahtjev za zaustavljanje nije moguće spremiti.');
        }
        chmod($this->path, 0600);
    }

    public function isRequested(): bool
    {
        clearstatcache(true, $this->path);
        return is_file($this->path);
    }

    public function clear(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }
}

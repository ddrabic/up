<?php

declare(strict_types=1);

namespace Upp\Import;

use RuntimeException;

final class JsonFileReader
{
    public function read(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('JSON datoteka nije pronađena.');
        }
        if (!is_readable($path)) {
            throw new RuntimeException('JSON datoteka nije čitljiva.');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('JSON datoteku nije moguće pročitati.');
        }
        try {
            $shape = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('JSON nije valjan: ' . $exception->getMessage() . ' Provjerite i završni zarez.', 0, $exception);
        }
        if (!is_array($shape) || !is_array($decoded) || !array_is_list($decoded)) {
            throw new RuntimeException('Korijenski JSON element mora biti niz zapisa.');
        }
        foreach ($shape as $index => $record) {
            if (!$record instanceof \stdClass) {
                throw new RuntimeException("Zapis #{$index} mora biti JSON objekt.");
            }
            if (property_exists($record, 'stanje')) {
                $stock = $record->stanje;
                if (!is_int($stock) && !is_float($stock) && !is_string($stock) && !$stock instanceof \stdClass) {
                    throw new RuntimeException("Zapis #{$index}: stanje mora biti broj, numerički string ili objekt skladišta.");
                }
            }
        }
        return $decoded;
    }
}

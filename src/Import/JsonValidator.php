<?php

declare(strict_types=1);

namespace Upp\Import;

use RuntimeException;

final class JsonValidator
{
    private const REQUIRED = ['kodRobe', 'nazivRobe', 'MPC', 'popust', 'aktivan', 'stanje'];

    public function validate(array $records): void
    {
        $skus = [];
        foreach ($records as $index => $record) {
            if (!is_array($record) || array_is_list($record)) {
                throw new RuntimeException("Zapis #{$index} mora biti JSON objekt.");
            }
            foreach (self::REQUIRED as $field) {
                if (!array_key_exists($field, $record)) {
                    throw new RuntimeException("Zapis #{$index}: nedostaje obvezno polje {$field}.");
                }
            }
            $sku = trim((string) $record['kodRobe']);
            if ($sku === '') {
                throw new RuntimeException("Zapis #{$index}: kodRobe ne smije biti prazan.");
            }
            if (trim((string) $record['nazivRobe']) === '') {
                throw new RuntimeException("Zapis #{$index}: nazivRobe ne smije biti prazan.");
            }
            if (!is_numeric($record['MPC'])) {
                throw new RuntimeException("Zapis #{$index}: MPC mora biti broj.");
            }
            if (!is_numeric($record['popust']) || (float) $record['popust'] < 0 || (float) $record['popust'] > 100) {
                throw new RuntimeException("Zapis #{$index}: popust mora biti između 0 i 100.");
            }
            if (!in_array($record['aktivan'], [0, 1, '0', '1'], true)) {
                throw new RuntimeException("Zapis #{$index}: aktivan mora biti 0 ili 1.");
            }
            if (isset($skus[$sku])) {
                throw new RuntimeException("Dupli kodRobe {$sku} u zapisima #{$skus[$sku]} i #{$index}.");
            }
            $skus[$sku] = $index;
        }
    }
}

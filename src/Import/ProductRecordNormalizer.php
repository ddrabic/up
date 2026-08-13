<?php

declare(strict_types=1);

namespace Upp\Import;

use RuntimeException;
use Upp\Domain\ProductRecord;
use Upp\Inventory\StockCalculator;

final class ProductRecordNormalizer
{
    public function __construct(
        private readonly StockCalculator $stockCalculator,
        private readonly array $includedWarehouses = [],
    ) {
    }

    public function normalize(array $record, int $index): ProductRecord
    {
        try {
            $stock = $this->stockCalculator->calculate($record['stanje'], $this->includedWarehouses);
        } catch (\InvalidArgumentException $exception) {
            throw new RuntimeException("Zapis #{$index}: {$exception->getMessage()}", 0, $exception);
        }

        return new ProductRecord(
            $index,
            trim((string) $record['kodRobe']),
            trim((string) $record['nazivRobe']),
            (float) $record['MPC'],
            (float) $record['popust'],
            (bool) (int) $record['aktivan'],
            $stock['total'],
            $stock['warehouses'],
            self::optionalString($record['kodGrupe'] ?? null),
            self::optionalString($record['kodGrupe2'] ?? null),
            self::optionalString($record['brand'] ?? null),
            self::optionalString($record['velicinaRame'] ?? null),
            self::optionalString($record['velicinaKotaca'] ?? null),
            self::optionalString($record['spol'] ?? null),
            isset($record['parentProductId']) && is_numeric($record['parentProductId']) ? (int) $record['parentProductId'] : null,
        );
    }

    private static function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}

<?php

declare(strict_types=1);

namespace Upp\Inventory;

use InvalidArgumentException;

final class StockCalculator
{
    /** @return array{total: float, warehouses: array<string, float>} */
    public function calculate(mixed $stock, array $includedWarehouses = []): array
    {
        if (is_int($stock) || is_float($stock) || (is_string($stock) && is_numeric($stock))) {
            return ['total' => (float) $stock, 'warehouses' => []];
        }
        // Prazan JSON objekt dekodira se u prazan PHP niz i predstavlja zalihu 0.
        if (!is_array($stock) || ($stock !== [] && array_is_list($stock))) {
            throw new InvalidArgumentException('stanje mora biti broj, numerički string ili objekt skladišta.');
        }

        $normalized = [];
        foreach ($stock as $warehouse => $quantity) {
            if (!is_int($quantity) && !is_float($quantity) && !(is_string($quantity) && is_numeric($quantity))) {
                throw new InvalidArgumentException(sprintf('Skladište %s ima nenumeričku količinu.', (string) $warehouse));
            }
            $normalized[(string) $warehouse] = (float) $quantity;
        }

        $selected = $normalized;
        if ($includedWarehouses !== []) {
            $selected = [];
            foreach ($includedWarehouses as $warehouse) {
                $key = (string) $warehouse;
                if (array_key_exists($key, $normalized)) {
                    $selected[$key] = $normalized[$key];
                }
            }
        }

        return ['total' => array_sum($selected), 'warehouses' => $normalized];
    }
}

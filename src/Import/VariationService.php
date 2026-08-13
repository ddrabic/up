<?php

declare(strict_types=1);

namespace Upp\Import;

use RuntimeException;
use Upp\Domain\ProductRecord;
use Upp\WooCommerce\WooCommerceGatewayInterface;

final class VariationService
{
    public function __construct(private readonly WooCommerceGatewayInterface $gateway)
    {
    }

    public function update(ProductRecord $record, array $existing, array $payload): array
    {
        $parentId = $existing['parent_id'] ?? null;
        if (!is_numeric($parentId) || (int) $parentId < 1) {
            throw new RuntimeException('WooCommerce resolver nije vratio valjan parent_id varijacije.');
        }
        // Polja proizvoda i struktura njegovih atributa nisu valjan payload za
        // endpoint varijacije. Ovdje sinkroniziramo cijenu i zalihu, a postojeće
        // atribute varijacije ostavljamo nepromijenjenima.
        unset($payload['name'], $payload['type'], $payload['status'], $payload['catalog_visibility'], $payload['categories'], $payload['attributes']);
        return $this->gateway->updateVariation((int) $parentId, (int) $existing['id'], $payload, $record->sku);
    }

    public function create(ProductRecord $record, array $payload): array
    {
        if ($record->parentProductId === null || $record->parentProductId < 1) {
            throw new RuntimeException('Nova varijacija zahtijeva pouzdan parentProductId.');
        }
        $payload['sku'] = $record->sku;
        unset($payload['name'], $payload['type'], $payload['status'], $payload['categories']);
        return $this->gateway->createVariation($record->parentProductId, $payload, $record->sku);
    }
}

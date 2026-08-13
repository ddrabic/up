<?php

declare(strict_types=1);

namespace Upp\Import;

use Upp\Domain\ProductRecord;
use Upp\Mapping\AttributeMapper;

final class ProductPayloadFactory
{
    public function __construct(
        private readonly AttributeMapper $attributeMapper,
        private readonly string $newProductStatus = 'draft',
        private readonly array $warehouseMap = [],
    ) {
    }

    public function create(ProductRecord $record, array $categoryIds, ?string $brandName): array
    {
        $payload = $this->common($record, $categoryIds, $brandName);
        return ['name' => $record->name, 'sku' => $record->sku, 'type' => 'simple', 'status' => $this->newProductStatus] + $payload;
    }

    public function update(ProductRecord $record, array $categoryIds, ?string $brandName): array
    {
        $payload = $this->common($record, $categoryIds, $brandName);
        $payload['name'] = $record->name;
        if (!$record->active && $record->totalStock == 0.0) {
            $payload['status'] = 'draft';
            $payload['catalog_visibility'] = 'hidden';
        }
        return $payload;
    }

    private function common(ProductRecord $record, array $categoryIds, ?string $brandName): array
    {
        $payload = [
            'regular_price' => number_format($record->regularPrice, 2, '.', ''),
            'sale_price' => $record->discount > 0
                ? number_format(round($record->regularPrice * (1 - $record->discount / 100), 2), 2, '.', '')
                : '',
            'manage_stock' => true,
            'stock_quantity' => $this->stockNumber($record->totalStock),
            'stock_status' => $record->totalStock > 0 ? 'instock' : 'outofstock',
            'attributes' => $this->attributeMapper->map($record, $brandName),
        ];
        if ($categoryIds !== []) {
            $payload['categories'] = array_map(static fn (int $id): array => ['id' => $id], $categoryIds);
        }
        if ($this->warehouseMap !== []) {
            $payload['meta_data'] = [[
                'key' => 'upp_warehouse_stock',
                'value' => $this->warehouseStock($record),
            ]];
        }
        return $payload;
    }

    /** @return array<string, int|float> */
    private function warehouseStock(ProductRecord $record): array
    {
        $stock = [];
        foreach ($this->warehouseMap as $code => $_location) {
            $quantity = (float) ($record->stockByWarehouse[(string) $code] ?? 0);
            $stock[(string) $code] = $this->stockNumber($quantity);
        }
        return $stock;
    }

    private function stockNumber(float $stock): int|float
    {
        return floor($stock) === $stock ? (int) $stock : $stock;
    }
}

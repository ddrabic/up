<?php

declare(strict_types=1);

namespace Upp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Upp\Domain\ProductRecord;
use Upp\Import\ProductPayloadFactory;
use Upp\Mapping\AttributeMapper;

final class PayloadFactoryTest extends TestCase
{
    public function testRestV3CreatePayloadAndPrices(): void
    {
        $factory = new ProductPayloadFactory(new AttributeMapper());
        $payload = $factory->create($this->record(10, true, 0), [8], 'Scott');
        self::assertSame('Naziv', $payload['name']);
        self::assertSame('SKU', $payload['sku']);
        self::assertSame('draft', $payload['status']);
        self::assertSame('819.00', $payload['regular_price']);
        self::assertSame('', $payload['sale_price']);
        self::assertTrue($payload['manage_stock']);
        self::assertSame('instock', $payload['stock_status']);
        self::assertArrayNotHasKey('product', $payload);
        self::assertArrayNotHasKey('price', $payload);
        self::assertSame(['M'], $payload['attributes'][0]['options']);
        self::assertSame('Veličina okvira', $payload['attributes'][0]['name']);
    }

    public function testDiscountAndInactiveUpdatePayload(): void
    {
        $factory = new ProductPayloadFactory(new AttributeMapper());
        self::assertSame('737.10', $factory->update($this->record(10, true, 10), [], null)['sale_price']);
        $payload = $factory->update($this->record(0, false, 0), [], null);
        self::assertSame('draft', $payload['status']);
        self::assertSame('hidden', $payload['catalog_visibility']);
        self::assertSame('outofstock', $payload['stock_status']);
        self::assertSame(0, $payload['stock_quantity']);
    }

    public function testWarehouseStockIsACompleteReplacementSnapshot(): void
    {
        $factory = new ProductPayloadFactory(
            new AttributeMapper(),
            'draft',
            ['202' => ['name' => 'Čakovec'], '204' => ['name' => 'Virovitica']],
        );
        $record = $this->record(4, true, 0, ['202' => 4]);

        $payload = $factory->update($record, [], null);

        self::assertSame('upp_warehouse_stock', $payload['meta_data'][0]['key']);
        self::assertSame(['202' => 4, '204' => 0], $payload['meta_data'][0]['value']);
    }

    private function record(float $stock, bool $active, float $discount, array $stockByWarehouse = []): ProductRecord
    {
        return new ProductRecord(0, 'SKU', 'Naziv', 819, $discount, $active, $stock, $stockByWarehouse, '1083', null, 'SC', 'M', '29', 'muški');
    }
}

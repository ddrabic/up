<?php

declare(strict_types=1);

namespace Upp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Upp\Domain\ProductRecord;
use Upp\Import\VariationService;
use Upp\Tests\Support\FakeGateway;

final class VariationServiceTest extends TestCase
{
    public function testVariationRequiresReliableParent(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('parent_id');
        (new VariationService(new FakeGateway()))->update($this->record(), ['id' => 12, 'type' => 'variation'], []);
    }

    public function testVariationUsesDedicatedEndpointMethod(): void
    {
        $gateway = new FakeGateway();
        $response = (new VariationService($gateway))->update($this->record(), ['id' => 12, 'type' => 'variation', 'parent_id' => 7], [
            'name' => 'Varijacija',
            'categories' => [['id' => 3]],
            'attributes' => [['name' => 'Veličina', 'options' => ['M']]],
            'stock_quantity' => 2,
        ]);
        self::assertSame(12, $response['id']);
        self::assertSame(7, $gateway->updatedPayloads['VAR']['_parent']);
        self::assertSame(2, $gateway->updatedPayloads['VAR']['stock_quantity']);
        self::assertArrayNotHasKey('name', $gateway->updatedPayloads['VAR']);
        self::assertArrayNotHasKey('categories', $gateway->updatedPayloads['VAR']);
        self::assertArrayNotHasKey('attributes', $gateway->updatedPayloads['VAR']);
    }

    public function testNewVariationUsesExplicitParent(): void
    {
        $gateway = new FakeGateway();
        $record = new ProductRecord(0, 'NEW-VAR', 'Varijacija', 10, 0, true, 2, [], null, null, null, 'M', null, null, 9);
        $response = (new VariationService($gateway))->create($record, ['name' => 'Varijacija', 'type' => 'simple', 'regular_price' => '10.00']);
        self::assertSame(101, $response['id']);
        self::assertSame(9, $gateway->createdPayloads['NEW-VAR']['_parent']);
        self::assertArrayNotHasKey('name', $gateway->createdPayloads['NEW-VAR']);
    }

    private function record(): ProductRecord
    {
        return new ProductRecord(0, 'VAR', 'Varijacija', 10, 0, true, 2, [], null, null, null, 'M', null, null);
    }
}

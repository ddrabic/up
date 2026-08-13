<?php

declare(strict_types=1);

namespace Upp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Upp\Import\ImportService;
use Upp\Import\JsonFileReader;
use Upp\Import\JsonValidator;
use Upp\Import\ProductPayloadFactory;
use Upp\Import\ProductRecordNormalizer;
use Upp\Inventory\StockCalculator;
use Upp\Mapping\AttributeMapper;
use Upp\Mapping\BrandMapper;
use Upp\Mapping\CategoryMapper;
use Upp\Tests\Support\FakeGateway;

final class ImportServiceTest extends TestCase
{
    public function testInvalidJsonStopsBeforeGateway(): void
    {
        $gateway = new FakeGateway();
        $path = $this->file('[{"broken":,}]');
        try {
            $this->service($gateway)->import($path);
            self::fail('Invalid JSON accepted.');
        } catch (\RuntimeException) {
            self::assertSame(0, $gateway->calls);
        } finally {
            unlink($path);
        }
    }

    public function testInvalidWarehouseQuantityStopsBeforeGateway(): void
    {
        $gateway = new FakeGateway();
        $record = $this->record('BAD-STOCK');
        $record['stanje'] = ['202' => 'nije broj'];
        $path = $this->records([$record]);
        try {
            $this->service($gateway)->import($path);
            self::fail('Invalid stock accepted.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('nenumeričku količinu', $exception->getMessage());
            self::assertSame(0, $gateway->calls);
        } finally {
            unlink($path);
        }
    }

    public function testCreateUpdateAndSkuResultRules(): void
    {
        $gateway = new FakeGateway();
        $gateway->categories['scott'] = 9;
        $gateway->resolvedBySku['UPDATE'] = ['id' => 7, 'sku' => 'UPDATE', 'type' => 'simple', 'parent_id' => null];
        $path = $this->records([$this->record('CREATE'), $this->record('UPDATE')]);
        $result = $this->service($gateway)->import($path);
        unlink($path);
        self::assertSame('create', $result['results'][0]->operation);
        self::assertSame('update', $result['results'][1]->operation);
        self::assertArrayHasKey('attributes', $gateway->updatedPayloads['UPDATE']);
        self::assertSame(1, $result['summary']['created']);
        self::assertSame(1, $result['summary']['updated']);
    }

    public function testResultCallbackRunsImmediatelyForEveryProcessedRecord(): void
    {
        $gateway = new FakeGateway();
        $path = $this->records([$this->record('FIRST'), $this->record('SECOND')]);
        $observed = [];

        $result = $this->service($gateway)->import(
            $path,
            static function ($item) use (&$observed): void {
                $observed[] = $item->sku;
            },
        );
        unlink($path);

        self::assertSame(['FIRST', 'SECOND'], $observed);
        self::assertCount(2, $result['results']);
    }

    public function testVariationIsFoundBySkuWithoutParentIdAndUpdated(): void
    {
        $gateway = new FakeGateway();
        $gateway->resolvedBySku['VAR-M'] = ['id' => 81, 'sku' => 'VAR-M', 'type' => 'variation', 'parent_id' => 44];
        $gateway->variationPages[44][1] = [
            ['id' => 81, 'sku' => 'VAR-M', 'stock_quantity' => 6],
            ['id' => 82, 'sku' => 'OTHER', 'stock_quantity' => 3],
        ];
        $path = $this->records([$this->record('VAR-M', true, 6)]);

        $result = $this->service($gateway)->import($path);
        unlink($path);

        self::assertSame('update', $result['results'][0]->operation);
        self::assertSame(81, $result['results'][0]->productId);
        self::assertSame(44, $gateway->updatedPayloads['VAR-M']['_parent']);
        self::assertSame(6, $gateway->updatedPayloads['VAR-M']['stock_quantity']);
        self::assertArrayNotHasKey('name', $gateway->updatedPayloads['VAR-M']);
        self::assertArrayNotHasKey('categories', $gateway->updatedPayloads['VAR-M']);
        self::assertArrayNotHasKey('attributes', $gateway->updatedPayloads['VAR-M']);
        self::assertArrayNotHasKey('VAR-M', $gateway->createdPayloads);
        self::assertSame(44, $gateway->updatedProducts[0]['id']);
        self::assertSame([
            ['key' => 'upp_variations_total_stock', 'value' => 9],
        ], $gateway->updatedProducts[0]['payload']['meta_data']);
        self::assertArrayNotHasKey('manage_stock', $gateway->updatedProducts[0]['payload']);
    }

    public function testSimpleResolverResultUsesProductUpdate(): void
    {
        $gateway = new FakeGateway();
        $gateway->resolvedBySku['SIMPLE'] = ['id' => 100, 'sku' => 'SIMPLE', 'type' => 'simple', 'parent_id' => null];
        $path = $this->records([$this->record('SIMPLE')]);

        $result = $this->service($gateway)->import($path);
        unlink($path);

        self::assertSame('update', $result['results'][0]->operation);
        self::assertSame(100, $gateway->updatedProducts[0]['id']);
        self::assertSame('SIMPLE', $gateway->updatedProducts[0]['sku']);
    }

    public function testVariationStockPayloadForZeroAndPositiveStock(): void
    {
        $gateway = new FakeGateway();
        $gateway->resolvedBySku['VAR-5'] = ['id' => 102, 'sku' => 'VAR-5', 'type' => 'variation', 'parent_id' => 100];
        $gateway->resolvedBySku['VAR-0'] = ['id' => 103, 'sku' => 'VAR-0', 'type' => 'variation', 'parent_id' => 100];
        $path = $this->records([$this->record('VAR-5', true, 5), $this->record('VAR-0', true, 0)]);
        $result = $this->service($gateway)->import($path);
        unlink($path);

        self::assertSame(2, $result['summary']['updated']);
        self::assertSame(['manage_stock' => true, 'stock_quantity' => 5, 'stock_status' => 'instock'], array_intersect_key(
            $gateway->updatedPayloads['VAR-5'], array_flip(['manage_stock', 'stock_quantity', 'stock_status'])
        ));
        self::assertSame(['manage_stock' => true, 'stock_quantity' => 0, 'stock_status' => 'outofstock'], array_intersect_key(
            $gateway->updatedPayloads['VAR-0'], array_flip(['manage_stock', 'stock_quantity', 'stock_status'])
        ));
        self::assertCount(1, $gateway->updatedProducts);
    }

    public function testBusinessStatusRulesAndUnknownMappings(): void
    {
        $gateway = new FakeGateway();
        $gateway->resolvedBySku['INACTIVE_EXISTING'] = ['id' => 4, 'sku' => 'INACTIVE_EXISTING', 'type' => 'simple', 'parent_id' => null];
        $records = [
            $this->record('ACTIVE_ZERO', true, 0),
            $this->record('INACTIVE_NEW', false, 0),
            $this->record('INACTIVE_EXISTING', false, 0),
            $this->record('CONTRADICT', false, 2),
            $this->record('UNKNOWN', true, 1, 'XX', '9999'),
        ];
        $path = $this->records($records);
        $result = $this->service($gateway)->import($path);
        unlink($path);
        self::assertSame('outofstock', $gateway->createdPayloads['ACTIVE_ZERO']['stock_status']);
        self::assertSame('skip', $result['results'][1]->operation);
        self::assertSame('hidden', $gateway->updatedPayloads['INACTIVE_EXISTING']['catalog_visibility']);
        self::assertSame('skipped_with_warning', $result['results'][3]->operation);
        self::assertStringContainsString('Nepoznat brand', $result['results'][4]->message);
        self::assertStringContainsString('Nema mapiranja kategorije', $result['results'][4]->message);
    }

    private function service(FakeGateway $gateway): ImportService
    {
        return new ImportService(
            new JsonFileReader(), new JsonValidator(),
            new ProductRecordNormalizer(new StockCalculator()),
            new ProductPayloadFactory(new AttributeMapper()),
            new CategoryMapper(['1083' => 'scott']), new BrandMapper(['SC' => 'Scott']),
            $gateway
        );
    }

    private function record(string $sku, bool $active = true, float $stock = 1, string $brand = 'SC', string $category = '1083'): array
    {
        return ['kodRobe' => $sku, 'nazivRobe' => 'Naziv', 'MPC' => 10, 'popust' => 0, 'aktivan' => $active ? 1 : 0, 'stanje' => $stock, 'brand' => $brand, 'kodGrupe' => $category];
    }

    private function records(array $records): string
    {
        return $this->file(json_encode($records, JSON_THROW_ON_ERROR));
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'upp-test-');
        file_put_contents($path, $contents);
        return $path;
    }
}

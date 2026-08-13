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
        $gateway->productsBySku['UPDATE'] = [['id' => 7, 'sku' => 'UPDATE', 'type' => 'simple']];
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
        $gateway->productPages[1] = [
            ['id' => 44, 'sku' => 'PARENT', 'type' => 'variable'],
            ['id' => 45, 'sku' => 'SIMPLE', 'type' => 'simple'],
        ];
        $gateway->variationPages[44][1] = [
            ['id' => 81, 'sku' => 'VAR-M'],
            ['id' => 82, 'sku' => 'OTHER'],
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
    }

    public function testVariationIndexDetectsDuplicateSkuAcrossParents(): void
    {
        $gateway = new FakeGateway();
        $gateway->productPages[1] = [
            ['id' => 44, 'type' => 'variable'],
            ['id' => 55, 'type' => 'variable'],
        ];
        $gateway->variationPages[44][1] = [['id' => 81, 'sku' => 'DUP-VAR']];
        $gateway->variationPages[55][1] = [['id' => 91, 'sku' => 'DUP-VAR']];
        $path = $this->records([$this->record('DUP-VAR')]);

        $result = $this->service($gateway)->import($path);
        unlink($path);

        self::assertSame('error', $result['results'][0]->operation);
        self::assertStringContainsString('više WooCommerce proizvoda', $result['results'][0]->message);
    }

    public function testNoOneAndDuplicateSkuResponses(): void
    {
        $gateway = new FakeGateway();
        $gateway->productsBySku['DUP'] = [['id' => 1, 'sku' => 'DUP'], ['id' => 2, 'sku' => 'DUP']];
        $path = $this->records([$this->record('DUP')]);
        $result = $this->service($gateway)->import($path);
        unlink($path);
        self::assertSame('error', $result['results'][0]->operation);
        self::assertStringContainsString('više WooCommerce proizvoda', $result['results'][0]->message);
    }

    public function testBusinessStatusRulesAndUnknownMappings(): void
    {
        $gateway = new FakeGateway();
        $gateway->productsBySku['INACTIVE_EXISTING'] = [['id' => 4, 'sku' => 'INACTIVE_EXISTING', 'type' => 'simple']];
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

<?php

declare(strict_types=1);

namespace Upp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Upp\Import\ImportService;
use Upp\Import\ImportCancelledException;
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

    public function testErpNameIsUsedOnlyWhenCreatingProducts(): void
    {
        $gateway = new FakeGateway();
        $gateway->resolvedBySku['EXISTING'] = [
            'id' => 7, 'sku' => 'EXISTING', 'type' => 'simple',
            'name' => 'Ručno uređen naziv',
        ];
        $gateway->resolvedBySku['INACTIVE'] = [
            'id' => 8, 'sku' => 'INACTIVE', 'type' => 'simple',
            'name' => 'Postojeći neaktivni proizvod',
        ];
        $records = [
            $this->record('NEW'),
            $this->record('EXISTING'),
            $this->record('INACTIVE', false, 0),
        ];
        foreach ($records as &$record) {
            $record['nazivRobe'] = '>GUMA RUBENA';
        }
        unset($record);
        $path = $this->records($records);
        try {
            $result = $this->service($gateway)->import($path);
        } finally {
            unlink($path);
        }

        self::assertSame(1, $result['summary']['created']);
        self::assertSame(2, $result['summary']['updated']);
        self::assertSame('>GUMA RUBENA', $gateway->createdPayloads['NEW']['name']);
        self::assertArrayNotHasKey('name', $gateway->updatedPayloads['EXISTING']);
        self::assertSame('Postojeći neaktivni proizvod #0#', $gateway->updatedPayloads['INACTIVE']['name']);
        foreach (['EXISTING', 'INACTIVE'] as $sku) {
            self::assertSame('10.00', $gateway->updatedPayloads[$sku]['regular_price']);
        }
        self::assertSame(1, $gateway->updatedPayloads['EXISTING']['stock_quantity']);
        self::assertSame(0, $gateway->updatedPayloads['INACTIVE']['stock_quantity']);
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

    public function testDecimalStockIsSafelyFlooredAndReportedToTheUser(): void
    {
        $gateway = new FakeGateway();
        $path = $this->records([$this->record('DECIMAL', true, 4.529999999999999)]);

        try {
            $result = $this->service($gateway)->import($path);
        } finally {
            unlink($path);
        }

        self::assertSame(4, $gateway->createdPayloads['DECIMAL']['stock_quantity']);
        self::assertStringContainsString(
            'Decimalna ERP zaliha 4,53 prilagođena je na 4',
            $result['results'][0]->message,
        );
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

    public function testResolverDetailsAvoidRedundantProductReadWithCompatibleFallback(): void
    {
        $gateway = new FakeGateway();
        $gateway->resolvedBySku['OPTIMIZED'] = [
            'id' => 100, 'sku' => 'OPTIMIZED', 'type' => 'simple', 'parent_id' => null,
            'name' => 'Postojeći naziv', 'categories' => [['id' => 8]],
        ];
        $gateway->resolvedBySku['LEGACY'] = [
            'id' => 101, 'sku' => 'LEGACY', 'type' => 'simple', 'parent_id' => null,
        ];
        $path = $this->records([$this->record('OPTIMIZED'), $this->record('LEGACY')]);

        try {
            $result = $this->service($gateway)->import($path);
        } finally {
            unlink($path);
        }

        self::assertSame(2, $result['summary']['updated']);
        self::assertSame(1, $gateway->getProductCalls);
    }

    public function testExistingSimpleProductsAreUpdatedInSmallBatches(): void
    {
        $gateway = new FakeGateway();
        $records = [];
        for ($index = 1; $index <= 11; $index++) {
            $sku = 'BATCH-' . $index;
            $gateway->resolvedBySku[$sku] = [
                'id' => 100 + $index, 'sku' => $sku, 'type' => 'simple', 'parent_id' => null,
                'name' => 'Postojeći naziv ' . $index, 'categories' => [],
            ];
            $records[] = $this->record($sku);
        }
        $path = $this->records($records);

        try {
            $result = $this->service($gateway)->import($path);
        } finally {
            unlink($path);
        }

        self::assertSame(11, $result['summary']['updated']);
        self::assertSame(2, $gateway->batchUpdateCalls);
        self::assertSame('BATCH-1', $result['results'][0]->sku);
        self::assertSame('BATCH-11', $result['results'][10]->sku);
    }

    public function testFailedBatchUpdateFallsBackToPerProductUpdates(): void
    {
        $gateway = new FakeGateway();
        $gateway->batchUpdateError = new \RuntimeException('Batch nije dostupan.');
        foreach (['A', 'B'] as $index => $sku) {
            $gateway->resolvedBySku[$sku] = [
                'id' => 10 + $index, 'sku' => $sku, 'type' => 'simple', 'parent_id' => null,
                'name' => 'Postojeći naziv', 'categories' => [],
            ];
        }
        $path = $this->records([$this->record('A'), $this->record('B')]);

        try {
            $result = $this->service($gateway)->import($path);
        } finally {
            unlink($path);
        }

        self::assertSame(2, $result['summary']['updated']);
        self::assertSame(1, $gateway->batchUpdateCalls);
        self::assertCount(2, $gateway->updatedProducts);
    }

    public function testCancellationStopsAfterTheCurrentlyRunningBatch(): void
    {
        $gateway = new FakeGateway();
        $records = [];
        for ($index = 1; $index <= 15; $index++) {
            $sku = 'STOP-' . $index;
            $gateway->resolvedBySku[$sku] = [
                'id' => 200 + $index, 'sku' => $sku, 'type' => 'simple', 'parent_id' => null,
                'name' => 'Postojeći naziv ' . $index, 'categories' => [],
            ];
            $records[] = $this->record($sku);
        }
        $path = $this->records($records);

        try {
            $this->service($gateway)->import(
                $path,
                null,
                static fn (): bool => $gateway->batchUpdateCalls >= 1,
            );
            self::fail('Import nije zaustavljen.');
        } catch (ImportCancelledException $exception) {
            self::assertSame(10, $exception->processed);
            self::assertSame(1, $gateway->batchUpdateCalls);
            self::assertCount(10, $gateway->updatedProducts);
        } finally {
            unlink($path);
        }
    }

    public function testProgressReportsTotalRecordNumbersAndBatchSkus(): void
    {
        $gateway = new FakeGateway();
        foreach (['FIRST', 'SECOND'] as $index => $sku) {
            $gateway->resolvedBySku[$sku] = [
                'id' => 300 + $index, 'sku' => $sku, 'type' => 'simple', 'parent_id' => null,
                'name' => 'Postojeći naziv', 'categories' => [],
            ];
        }
        $path = $this->records([$this->record('FIRST'), $this->record('SECOND')]);
        $progress = [];

        try {
            $this->service($gateway)->import(
                $path,
                null,
                null,
                static function (array $event) use (&$progress): void {
                    $progress[] = $event;
                },
            );
        } finally {
            unlink($path);
        }

        self::assertSame(['stage' => 'ready', 'total' => 2, 'offset' => 0, 'chunkTotal' => 2], $progress[0]);
        $batch = array_values(array_filter($progress, static fn (array $event): bool => $event['stage'] === 'updating_batch'));
        self::assertCount(1, $batch);
        self::assertSame(1, $batch[0]['from']);
        self::assertSame(2, $batch[0]['to']);
        self::assertSame(['FIRST', 'SECOND'], $batch[0]['skus']);
    }

    public function testImportRangeProcessesOnlyRequestedRecordsAndKeepsOriginalIndexes(): void
    {
        $gateway = new FakeGateway();
        $path = $this->records([
            $this->record('FIRST'),
            $this->record('SECOND'),
            $this->record('THIRD'),
        ]);

        try {
            $result = $this->service($gateway)->import($path, null, null, null, 1, 1);
        } finally {
            unlink($path);
        }

        self::assertCount(1, $result['results']);
        self::assertSame('SECOND', $result['results'][0]->sku);
        self::assertSame(1, $result['results'][0]->index);
        self::assertSame(3, $result['sourceTotal']);
        self::assertSame(2, $result['nextOffset']);
        self::assertTrue($result['hasMore']);
        self::assertArrayNotHasKey('FIRST', $gateway->createdPayloads);
        self::assertArrayNotHasKey('THIRD', $gateway->createdPayloads);
    }

    public function testStartPositionPastEndFinishesWithoutWooCommerceCalls(): void
    {
        $gateway = new FakeGateway();
        $path = $this->records([$this->record('ONLY')]);

        try {
            $result = $this->service($gateway)->import($path, null, null, null, 999, 100);
        } finally {
            unlink($path);
        }

        self::assertSame(0, $result['summary']['total']);
        self::assertSame([], $result['results']);
        self::assertSame(1, $result['sourceTotal']);
        self::assertSame(999, $result['nextOffset']);
        self::assertFalse($result['hasMore']);
        self::assertSame(0, $gateway->calls);
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

    public function testInactiveMarkerLifecyclePreservesNameAndCategoriesWithoutRepublishing(): void
    {
        $gateway = new FakeGateway();
        $gateway->categories['brisati'] = 99;
        $gateway->resolvedBySku['LIFECYCLE'] = ['id' => 7, 'type' => 'simple', 'sku' => 'LIFECYCLE'];
        $gateway->productDetails[7] = [
            'id' => 7, 'name' => '>Ručno uređen naziv', 'status' => 'publish',
            'categories' => [['id' => 12], ['id' => 13]],
        ];
        $path = $this->records([$this->record('LIFECYCLE', false, 0, 'XX', '9999')]);
        try {
            $service = $this->service($gateway, 'skip_product');
            $result = $service->import($path);
            self::assertSame('update', $result['results'][0]->operation);
            $payload = $gateway->updatedPayloads['LIFECYCLE'];
            self::assertSame('>Ručno uređen naziv #0#', $payload['name']);
            self::assertSame('draft', $payload['status']);
            self::assertSame('hidden', $payload['catalog_visibility']);
            self::assertSame([['id' => 12], ['id' => 13], ['id' => 99]], $payload['categories']);

            $gateway->productDetails[7] = array_replace($gateway->productDetails[7], $payload);
            $service->import($path);
            self::assertArrayNotHasKey('name', $gateway->updatedPayloads['LIFECYCLE']);
            self::assertCount(3, $gateway->updatedPayloads['LIFECYCLE']['categories']);
        } finally {
            unlink($path);
        }

        $path = $this->records([$this->record('LIFECYCLE', true, 0, 'SC', '9999')]);
        try {
            $this->service($gateway)->import($path);
            $payload = $gateway->updatedPayloads['LIFECYCLE'];
            self::assertSame('>Ručno uređen naziv', $payload['name']);
            self::assertSame([['id' => 12], ['id' => 13]], $payload['categories']);
            self::assertArrayNotHasKey('status', $payload);
            self::assertArrayNotHasKey('catalog_visibility', $payload);
        } finally {
            unlink($path);
        }
    }

    public function testMissingRetirementCategoryStillDraftsAndWarns(): void
    {
        $gateway = new FakeGateway();
        $gateway->resolvedBySku['OLD'] = ['id' => 7, 'type' => 'simple'];
        $path = $this->records([$this->record('OLD', false, 0)]);
        try {
            $result = $this->service($gateway)->import($path);
            self::assertSame('draft', $gateway->updatedPayloads['OLD']['status']);
            self::assertArrayNotHasKey('categories', $gateway->updatedPayloads['OLD']);
            self::assertStringContainsString('Brisati', $result['results'][0]->message);
        } finally {
            unlink($path);
        }
    }

    public function testInvalidExistingNamePreventsUpdateInsteadOfUsingErpName(): void
    {
        foreach ([null, '#0#'] as $name) {
            $gateway = new FakeGateway();
            $gateway->resolvedBySku['BAD'] = ['id' => 7, 'type' => 'simple'];
            $gateway->productDetails[7] = ['id' => 7, 'name' => $name];
            $path = $this->records([$this->record('BAD')]);
            try {
                $result = $this->service($gateway)->import($path);
                self::assertSame('error', $result['results'][0]->operation);
                self::assertSame([], $gateway->updatedProducts);
            } finally {
                unlink($path);
            }
        }
    }

    public function testInactiveVariationIsDraftedWithoutRetiringParent(): void
    {
        $gateway = new FakeGateway();
        $gateway->resolvedBySku['VAR'] = ['id' => 8, 'type' => 'variation', 'parent_id' => 7];
        $path = $this->records([$this->record('VAR', false, 0)]);
        try {
            $this->service($gateway)->import($path);
            $payload = $gateway->updatedPayloads['VAR'];
            self::assertSame('draft', $payload['status']);
            self::assertContains(['key' => 'upp_inactive_marker', 'value' => '#0#'], $payload['meta_data']);
            self::assertArrayNotHasKey('name', $payload);
            self::assertArrayNotHasKey('categories', $payload);
            self::assertArrayNotHasKey('status', $gateway->updatedProducts[0]['payload']);
        } finally {
            unlink($path);
        }
        $path = $this->records([$this->record('VAR', true, 2)]);
        try {
            $this->service($gateway)->import($path);
            self::assertContains(['key' => 'upp_inactive_marker', 'value' => ''], $gateway->updatedPayloads['VAR']['meta_data']);
            self::assertArrayNotHasKey('status', $gateway->updatedPayloads['VAR']);
        } finally {
            unlink($path);
        }
    }

    private function service(FakeGateway $gateway, string $unknownBehavior = 'skip_category'): ImportService
    {
        return new ImportService(
            new JsonFileReader(), new JsonValidator(),
            new ProductRecordNormalizer(new StockCalculator()),
            new ProductPayloadFactory(new AttributeMapper()),
            new CategoryMapper(['1083' => 'scott']), new BrandMapper(['SC' => 'Scott']),
            $gateway, null, $unknownBehavior, $unknownBehavior === 'skip_product' ? 'skip_product' : 'continue'
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

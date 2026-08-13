<?php

declare(strict_types=1);

namespace Upp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Upp\Import\JsonFileReader;
use Upp\Import\JsonValidator;
use Upp\Import\ProductRecordNormalizer;
use Upp\Inventory\StockCalculator;

final class StockAndJsonTest extends TestCase
{
    public function testScalarAndWarehouseStock(): void
    {
        $calculator = new StockCalculator();
        self::assertSame(5.0, $calculator->calculate('5.000')['total']);
        self::assertSame(90.0, $calculator->calculate(['2018' => 0, '202' => 4, '204' => 4, '205' => 5, '208' => 2, '209' => 2, '701' => 73])['total']);
        self::assertSame(16.0, $calculator->calculate(['2018' => 0, '202' => 4, '204' => 4, '205' => 2, '208' => 2, '209' => 2, '701' => 2])['total']);
        self::assertSame(6.0, $calculator->calculate(['202' => 4, '204' => 2, '701' => 50], ['202', '204'])['total']);
        self::assertSame(-3.0, $calculator->calculate(['202' => -3])['total']);
    }

    public function testProvidedNewFileExpectedStocks(): void
    {
        $path = dirname(__DIR__) . '/fixtures/new-stock.json';
        $records = (new JsonFileReader())->read($path);
        $normalizer = new ProductRecordNormalizer(new StockCalculator());
        self::assertSame(90.0, $normalizer->normalize($records[0], 0)->totalStock);
        self::assertSame(16.0, $normalizer->normalize($records[1], 1)->totalStock);
    }

    public function testOldFileFailsOnTrailingComma(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('završni zarez');
        (new JsonFileReader())->read(dirname(__DIR__) . '/fixtures/old-trailing-comma.json');
    }

    public function testMissingFieldAndDuplicateSkuAreRejected(): void
    {
        $validator = new JsonValidator();
        try {
            $validator->validate([['kodRobe' => 'A']]);
            self::fail('Missing field accepted.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('nedostaje obvezno polje', $exception->getMessage());
        }
        $record = $this->record();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dupli kodRobe');
        $validator->validate([$record, $record]);
    }

    public function testInvalidJsonIsRejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'upp-json-');
        file_put_contents($path, '[{"x":1},]');
        try {
            try {
                (new JsonFileReader())->read($path);
                self::fail('Invalid JSON accepted.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('JSON nije valjan', $exception->getMessage());
            }
        } finally {
            unlink($path);
        }
    }

    private function record(): array
    {
        return ['kodRobe' => 'A', 'nazivRobe' => 'Artikl', 'MPC' => 10, 'popust' => 0, 'aktivan' => 1, 'stanje' => 1];
    }
}

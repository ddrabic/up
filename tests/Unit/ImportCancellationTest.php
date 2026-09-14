<?php

declare(strict_types=1);

namespace Upp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Upp\Import\ImportCancellation;

final class ImportCancellationTest extends TestCase
{
    public function testRequestCanBeObservedAndCleared(): void
    {
        $importFile = tempnam(sys_get_temp_dir(), 'upp-import-file-');
        $cancellation = new ImportCancellation($importFile, '20260914-190000-abcdef');

        try {
            self::assertFalse($cancellation->isRequested());
            $cancellation->request();
            self::assertTrue($cancellation->isRequested());
            $cancellation->clear();
            self::assertFalse($cancellation->isRequested());
        } finally {
            $cancellation->clear();
            unlink($importFile);
        }
    }

    public function testInvalidImportIdIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Neispravan identifikator');
        new ImportCancellation('/tmp/import.json', '../../pogresan');
    }
}

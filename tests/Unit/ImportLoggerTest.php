<?php

declare(strict_types=1);

namespace Upp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Upp\Logging\ImportLogger;

final class ImportLoggerTest extends TestCase
{
    public function testStockParameterNamesAreNotMistakenForSecrets(): void
    {
        self::assertSame(
            'Nevaljani parametri: stock_quantity, stock_status',
            ImportLogger::sanitize('Nevaljani parametri: stock_quantity, stock_status'),
        );
    }

    public function testActualWooCommerceCredentialsRemainRedacted(): void
    {
        $key = 'ck_1234567890abcdef1234567890abcdef12345678';
        $secret = 'cs_1234567890abcdef1234567890abcdef12345678';

        self::assertSame(
            'Ključevi ck_REDACTED i cs_REDACTED nisu valjani.',
            ImportLogger::sanitize("Ključevi {$key} i {$secret} nisu valjani."),
        );
    }
}

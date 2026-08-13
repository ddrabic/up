<?php

declare(strict_types=1);

namespace Upp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Upp\WooCommerce\RestWooCommerceGateway;

final class StagingConnectionTest extends TestCase
{
    public function testConfiguredStagingConnection(): void
    {
        $url = getenv('UPP_STAGING_WOO_URL') ?: '';
        $key = getenv('UPP_STAGING_WOO_KEY') ?: '';
        $secret = getenv('UPP_STAGING_WOO_SECRET') ?: '';
        if ($url === '' || $key === '' || $secret === '') {
            self::markTestSkipped('Postavite UPP_STAGING_WOO_URL, UPP_STAGING_WOO_KEY i UPP_STAGING_WOO_SECRET.');
        }
        RestWooCommerceGateway::fromCredentials($url, $key, $secret, true)->checkConnection();
        self::assertTrue(true);
    }
}

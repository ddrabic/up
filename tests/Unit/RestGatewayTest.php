<?php

declare(strict_types=1);

namespace Upp\Tests\Unit;

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;
use Automattic\WooCommerce\HttpClient\Request;
use Automattic\WooCommerce\HttpClient\Response;
use PHPUnit\Framework\TestCase;
use Upp\WooCommerce\GatewayException;
use Upp\WooCommerce\RestWooCommerceGateway;

final class RestGatewayTest extends TestCase
{
    public function test401IsClassifiedAndNotRetried(): void
    {
        $client = new StubClient([$this->httpError(401, 'woocommerce_rest_cannot_view', 'Netočni ključevi')]);
        $gateway = new RestWooCommerceGateway($client, null, static fn () => null);
        try {
            $gateway->checkConnection();
            self::fail('401 accepted.');
        } catch (GatewayException $exception) {
            self::assertSame(401, $exception->httpStatus);
            self::assertStringContainsString('Autentikacija', $exception->getMessage());
            self::assertSame(1, $client->calls);
        }
    }

    public function test500IsNotRetriedBut503IsRetriedThreeTimes(): void
    {
        $error500 = $this->httpError(500, 'internal_error', 'Server error');
        $client500 = new StubClient([$error500]);
        try {
            (new RestWooCommerceGateway($client500, null, static fn () => null))->findProductsBySku('A');
        } catch (GatewayException $exception) {
            self::assertSame(500, $exception->httpStatus);
            self::assertSame(1, $client500->calls);
        }

        $client503 = new StubClient([
            $this->httpError(503, 'unavailable', 'Privremeno nedostupno'),
            $this->httpError(503, 'unavailable', 'Privremeno nedostupno'),
            [['id' => 8, 'sku' => 'A']],
        ]);
        $products = (new RestWooCommerceGateway($client503, null, static fn () => null))->findProductsBySku('A');
        self::assertSame(3, $client503->calls);
        self::assertSame(8, $products[0]['id']);
    }

    public function testZeroOneAndExactSkuFiltering(): void
    {
        $empty = new RestWooCommerceGateway(new StubClient([[]]));
        self::assertSame([], $empty->findProductsBySku('SKU'));
        $one = new RestWooCommerceGateway(new StubClient([[['id' => 4, 'sku' => 'SKU'], ['id' => 5, 'sku' => 'sku']]]));
        self::assertCount(1, $one->findProductsBySku('SKU'));
    }

    public function testSkuResolverReturnsSimpleAndVariationMetadata(): void
    {
        $simpleClient = new StubClient([[
            'found' => true, 'id' => 100, 'sku' => 'SIMPLE', 'type' => 'simple', 'parent_id' => null,
        ]]);
        $simple = new RestWooCommerceGateway($simpleClient);
        self::assertSame(
            ['id' => 100, 'sku' => 'SIMPLE', 'type' => 'simple', 'parent_id' => null],
            $simple->resolveProductBySku('SIMPLE'),
        );
        self::assertSame(['GET', 'upp/product-by-sku', ['sku' => 'SIMPLE']], $simpleClient->requests[0]);

        $variation = new RestWooCommerceGateway(new StubClient([[
            'found' => true, 'id' => 102, 'sku' => 'VAR', 'type' => 'variation', 'parent_id' => 100,
        ]]));
        self::assertSame(
            ['id' => 102, 'sku' => 'VAR', 'type' => 'variation', 'parent_id' => 100],
            $variation->resolveProductBySku('VAR'),
        );

        $missing = new RestWooCommerceGateway(new StubClient([['found' => false]]));
        self::assertNull($missing->resolveProductBySku('MISSING'));
    }

    public function testVariationUpdateUsesParentAndVariationEndpoint(): void
    {
        $client = new StubClient([['id' => 102, 'sku' => 'VAR']]);
        $gateway = new RestWooCommerceGateway($client);

        $gateway->updateVariation(100, 102, ['stock_quantity' => 5], 'VAR');

        self::assertSame(
            ['PUT', 'products/100/variations/102', ['stock_quantity' => 5]],
            $client->requests[0],
        );
    }

    public function testVariationPageReturnsVariationCollection(): void
    {
        $gateway = new RestWooCommerceGateway(new StubClient([[
            ['id' => 8, 'sku' => 'VAR-A'],
            'invalid',
        ]]));

        self::assertSame(
            [['id' => 8, 'sku' => 'VAR-A']],
            $gateway->variationsPage(17, 2, 50),
        );
    }

    public function testCurlErrorWithFalseResponseBodyKeepsTheConnectionError(): void
    {
        $curlError = new HttpClientException(
            'cURL Error: SSL certificate problem: self-signed certificate',
            0,
            new Request('https://wordpress.test/wp-json/wc/v3/products', 'GET'),
            new Response(0, [], false),
        );

        $gateway = new RestWooCommerceGateway(new StubClient([$curlError]));

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('SSL provjera nije uspjela');
        $gateway->checkConnection();
    }

    private function httpError(int $status, string $code, string $message): HttpClientException
    {
        return new HttpClientException(
            $message,
            $status,
            new Request('https://example.test/wp-json/wc/v3/products', 'GET'),
            new Response($status, [], json_encode(['code' => $code, 'message' => $message], JSON_THROW_ON_ERROR)),
        );
    }
}

final class StubClient extends Client
{
    public int $calls = 0;
    public array $requests = [];

    public function __construct(private array $responses)
    {
    }

    public function get($endpoint, $parameters = [])
    {
        $this->calls++;
        $this->requests[] = ['GET', $endpoint, $parameters];
        $response = array_shift($this->responses);
        if ($response instanceof \Throwable) throw $response;
        return $response;
    }

    public function put($endpoint, $data)
    {
        $this->calls++;
        $this->requests[] = ['PUT', $endpoint, $data];
        $response = array_shift($this->responses);
        if ($response instanceof \Throwable) throw $response;
        return $response;
    }
}

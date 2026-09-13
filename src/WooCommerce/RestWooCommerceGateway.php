<?php

declare(strict_types=1);

namespace Upp\WooCommerce;

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;
use Throwable;
use Upp\Logging\ImportLogger;

final class RestWooCommerceGateway implements WooCommerceGatewayInterface
{
    private array $categoryIdBySlug = [];
    private bool $categoriesLoaded = false;

    public function __construct(
        private readonly Client $client,
        private readonly ?ImportLogger $logger = null,
        private readonly mixed $sleeper = null,
    ) {
    }

    public static function fromCredentials(string $url, string $key, string $secret, bool $verifySsl, ?ImportLogger $logger = null): self
    {
        $url = rtrim(trim($url), '/');
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false || preg_match('~/wp-json(?:/wc/v3)?$~', $url)) {
            throw new GatewayException('Neispravan osnovni URL trgovine; ne dodajte /wp-json/wc/v3.');
        }
        if ($key === '' || $secret === '') {
            throw new GatewayException('WooCommerce API ključevi nisu konfigurirani.');
        }
        return new self(new Client($url, $key, $secret, [
            'version' => 'wc/v3',
            'timeout' => 30,
            'verify_ssl' => $verifySsl,
            'query_string_auth' => false,
            'user_agent' => 'UPP WooCommerce Importer',
        ]), $logger);
    }

    public function checkConnection(): void
    {
        try {
            $response = $this->request('GET', 'products', '', fn () => $this->client->get('products', ['per_page' => 1]));
            if (!is_array($response)) {
                throw new GatewayException('WooCommerce je vratio neispravan REST odgovor.');
            }
        } catch (GatewayException $exception) {
            $message = match ($exception->httpStatus) {
                401 => 'Autentikacija nije uspjela (401): provjerite WooCommerce ključeve.',
                403 => 'Pristup je zabranjen (403): REST ključ nema potrebna prava.',
                404 => 'REST v3 endpoint nije pronađen; provjerite osnovni URL i permalink postavke.',
                default => $this->connectionMessage($exception->getMessage()),
            };
            throw new GatewayException($message, $exception->httpStatus, $exception->wooCode, $exception->attempts, $exception);
        }
    }

    public function resolveProductBySku(string $sku): ?array
    {
        $endpoint = 'upp/product-by-sku';
        $resolved = $this->request('GET', $endpoint, $sku, fn () => $this->client->get($endpoint, ['sku' => $sku]));
        if (!is_array($resolved) || array_is_list($resolved) || !array_key_exists('found', $resolved)) {
            throw new GatewayException('Neispravan REST odgovor pri razrješavanju SKU-a.');
        }
        if ($resolved['found'] !== true) {
            return null;
        }
        if (!is_numeric($resolved['id'] ?? null) || !is_string($resolved['type'] ?? null)) {
            throw new GatewayException('REST resolver vratio je nepotpune podatke proizvoda.');
        }

        $parentId = $resolved['parent_id'] ?? null;
        return [
            'id' => (int) $resolved['id'],
            'sku' => (string) ($resolved['sku'] ?? $sku),
            'type' => $resolved['type'],
            'parent_id' => is_numeric($parentId) ? (int) $parentId : null,
        ];
    }

    public function getProduct(int $id, string $sku): array
    {
        $endpoint = 'products/' . $id;
        return $this->objectResponse($this->request('GET', $endpoint, $sku, fn () => $this->client->get($endpoint)));
    }

    public function findProductsBySku(string $sku): array
    {
        $products = $this->request('GET', 'products', $sku, fn () => $this->client->get('products', ['sku' => $sku, 'per_page' => 100]));
        if (!is_array($products)) {
            throw new GatewayException('Neispravan REST odgovor pri traženju proizvoda.');
        }
        return array_values(array_filter($products, static fn (mixed $product): bool => is_array($product) && (string) ($product['sku'] ?? '') === $sku));
    }

    public function productsPage(int $page, int $perPage = 100): array
    {
        $products = $this->request('GET', 'products', '', fn () => $this->client->get('products', [
            'page' => max(1, $page), 'per_page' => min(100, max(1, $perPage)),
        ]));
        if (!is_array($products)) {
            throw new GatewayException('Neispravan REST odgovor pri dohvatu proizvoda.');
        }
        return array_values(array_filter($products, 'is_array'));
    }

    public function findVariationsBySku(int $parentId, string $sku): array
    {
        $endpoint = "products/{$parentId}/variations";
        $variations = $this->request('GET', $endpoint, $sku, fn () => $this->client->get($endpoint, ['sku' => $sku, 'per_page' => 100]));
        if (!is_array($variations)) {
            throw new GatewayException('Neispravan REST odgovor pri traženju varijacije.');
        }
        return array_values(array_filter($variations, static fn (mixed $variation): bool => is_array($variation) && (string) ($variation['sku'] ?? '') === $sku));
    }

    public function variationsPage(int $parentId, int $page, int $perPage = 100): array
    {
        $endpoint = "products/{$parentId}/variations";
        $variations = $this->request('GET', $endpoint, '', fn () => $this->client->get($endpoint, [
            'page' => max(1, $page), 'per_page' => min(100, max(1, $perPage)),
        ]));
        if (!is_array($variations)) {
            throw new GatewayException('Neispravan REST odgovor pri dohvatu varijacija.');
        }
        return array_values(array_filter($variations, 'is_array'));
    }

    public function categoryIdBySlug(string $slug): ?int
    {
        if (!$this->categoriesLoaded) {
            $this->loadCategories();
        }
        return $this->categoryIdBySlug[strtolower($slug)] ?? null;
    }

    public function createProduct(array $payload, string $sku): array
    {
        return $this->objectResponse($this->request('POST', 'products', $sku, fn () => $this->client->post('products', $payload)));
    }

    public function updateProduct(int $id, array $payload, string $sku): array
    {
        $endpoint = 'products/' . $id;
        return $this->objectResponse($this->request('PUT', $endpoint, $sku, fn () => $this->client->put($endpoint, $payload)));
    }

    public function updateVariation(int $parentId, int $variationId, array $payload, string $sku): array
    {
        $endpoint = "products/{$parentId}/variations/{$variationId}";
        return $this->objectResponse($this->request('PUT', $endpoint, $sku, fn () => $this->client->put($endpoint, $payload)));
    }

    public function createVariation(int $parentId, array $payload, string $sku): array
    {
        $endpoint = "products/{$parentId}/variations";
        return $this->objectResponse($this->request('POST', $endpoint, $sku, fn () => $this->client->post($endpoint, $payload)));
    }

    private function loadCategories(): void
    {
        $page = 1;
        do {
            $categories = $this->request('GET', 'products/categories', '', fn () => $this->client->get('products/categories', ['page' => $page, 'per_page' => 100]));
            if (!is_array($categories)) {
                throw new GatewayException('Neispravan REST odgovor za kategorije.');
            }
            foreach ($categories as $category) {
                if (is_array($category) && isset($category['id'], $category['slug'])) {
                    $this->categoryIdBySlug[strtolower((string) $category['slug'])] = (int) $category['id'];
                }
            }
            $headers = $this->client->http->getResponse()?->getHeaders() ?? [];
            $totalPages = (int) ($headers['X-WP-TotalPages'] ?? $headers['x-wp-totalpages'] ?? 0);
            $hasNext = $totalPages > 0 ? $page < $totalPages : count($categories) === 100;
            $page++;
        } while ($hasNext);
        $this->categoriesLoaded = true;
    }

    private function request(string $operation, string $endpoint, string $sku, callable $request): mixed
    {
        $attempt = 0;
        do {
            $attempt++;
            try {
                return $this->normalize($request());
            } catch (Throwable $exception) {
                [$status, $wooCode, $message] = $this->details($exception);
                $this->logger?->apiError($operation, $endpoint, $sku, $status, $wooCode, $message, $attempt);
                $retryable = in_array($status, [429, 502, 503, 504], true) || ($status === null && $this->isTimeout($message));
                if (!$retryable || $attempt >= 3) {
                    throw new GatewayException(ImportLogger::sanitize($message), $status, $wooCode, $attempt, $exception);
                }
                $sleeper = $this->sleeper;
                if (is_callable($sleeper)) {
                    $sleeper($attempt);
                } else {
                    usleep(250000 * (2 ** ($attempt - 1)));
                }
            }
        } while (true);
    }

    private function details(Throwable $exception): array
    {
        $status = null;
        $wooCode = null;
        $message = $exception->getMessage();
        if ($exception instanceof HttpClientException) {
            $response = $exception->getResponse();
            $status = $response->getCode() ?: null;
            $responseBody = $response->getBody();
            if (is_string($responseBody) && $responseBody !== '') {
                $body = json_decode($responseBody, true);
                if (is_array($body)) {
                    $wooCode = isset($body['code']) ? (string) $body['code'] : null;
                    $message = isset($body['message']) ? (string) $body['message'] : $message;
                }
            }
        }
        return [$status, $wooCode, $message];
    }

    private function normalize(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalize($item);
            }
        }
        return $value;
    }

    private function objectResponse(mixed $response): array
    {
        if (!is_array($response) || array_is_list($response)) {
            throw new GatewayException('WooCommerce je vratio neispravan objekt proizvoda.');
        }
        return $response;
    }

    private function isTimeout(string $message): bool
    {
        return preg_match('/timed?\s*out|timeout|cURL Error:\s*28/i', $message) === 1;
    }

    private function connectionMessage(string $message): string
    {
        return match (true) {
            $this->isTimeout($message) => 'Isteklo je vrijeme povezivanja s WooCommerceom.',
            preg_match('/SSL|certificate/i', $message) === 1 => 'SSL provjera nije uspjela: ' . ImportLogger::sanitize($message),
            preg_match('/Could not resolve|Failed to connect|Connection refused/i', $message) === 1 => 'WooCommerce server nije dostupan.',
            preg_match('/JSON|decode|unexpected response/i', $message) === 1 => 'WooCommerce je vratio neispravan REST odgovor.',
            default => ImportLogger::sanitize($message),
        };
    }
}

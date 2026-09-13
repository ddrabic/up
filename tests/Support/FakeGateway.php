<?php

declare(strict_types=1);

namespace Upp\Tests\Support;

use Upp\WooCommerce\WooCommerceGatewayInterface;

final class FakeGateway implements WooCommerceGatewayInterface
{
    public int $calls = 0;
    public array $productsBySku = [];
    public array $productDetails = [];
    public array $resolvedBySku = [];
    public array $productPages = [];
    public array $variationPages = [];
    public array $categories = [];
    public array $createdPayloads = [];
    public array $updatedPayloads = [];
    public array $updatedProducts = [];
    public ?\Throwable $connectionError = null;

    public function checkConnection(): void
    {
        $this->calls++;
        if ($this->connectionError) throw $this->connectionError;
    }

    public function resolveProductBySku(string $sku): ?array
    {
        $this->calls++;
        return $this->resolvedBySku[$sku] ?? null;
    }

    public function getProduct(int $id, string $sku): array
    {
        $this->calls++;
        return $this->productDetails[$id] ?? ($this->resolvedBySku[$sku] + ['name' => 'Postojeći naziv', 'categories' => []]);
    }

    public function findProductsBySku(string $sku): array
    {
        $this->calls++;
        return $this->productsBySku[$sku] ?? [];
    }

    public function productsPage(int $page, int $perPage = 100): array
    {
        $this->calls++;
        return $this->productPages[$page] ?? [];
    }

    public function findVariationsBySku(int $parentId, string $sku): array
    {
        $this->calls++;
        return $this->productsBySku[$sku] ?? [];
    }

    public function variationsPage(int $parentId, int $page, int $perPage = 100): array
    {
        $this->calls++;
        return $this->variationPages[$parentId][$page] ?? [];
    }

    public function categoryIdBySlug(string $slug): ?int
    {
        $this->calls++;
        return $this->categories[$slug] ?? null;
    }

    public function createProduct(array $payload, string $sku): array
    {
        $this->calls++;
        $this->createdPayloads[$sku] = $payload;
        return ['id' => 100, 'sku' => $sku];
    }

    public function updateProduct(int $id, array $payload, string $sku): array
    {
        $this->calls++;
        $this->updatedPayloads[$sku] = $payload;
        $this->updatedProducts[] = ['id' => $id, 'sku' => $sku, 'payload' => $payload];
        return ['id' => $id, 'sku' => $sku];
    }

    public function updateVariation(int $parentId, int $variationId, array $payload, string $sku): array
    {
        $this->calls++;
        $this->updatedPayloads[$sku] = $payload + ['_parent' => $parentId];
        return ['id' => $variationId, 'sku' => $sku];
    }

    public function createVariation(int $parentId, array $payload, string $sku): array
    {
        $this->calls++;
        $this->createdPayloads[$sku] = $payload + ['_parent' => $parentId];
        return ['id' => 101, 'sku' => $sku];
    }
}

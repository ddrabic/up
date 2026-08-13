<?php

declare(strict_types=1);

namespace Upp\WooCommerce;

interface WooCommerceGatewayInterface
{
    public function checkConnection(): void;

    /** @return list<array<string, mixed>> */
    public function productsPage(int $page, int $perPage = 100): array;

    /** @return list<array<string, mixed>> */
    public function findProductsBySku(string $sku): array;

    /** @return list<array<string, mixed>> */
    public function findVariationsBySku(int $parentId, string $sku): array;

    /** @return list<array<string, mixed>> */
    public function variationsPage(int $parentId, int $page, int $perPage = 100): array;

    public function categoryIdBySlug(string $slug): ?int;

    /** @return array<string, mixed> */
    public function createProduct(array $payload, string $sku): array;

    /** @return array<string, mixed> */
    public function updateProduct(int $id, array $payload, string $sku): array;

    /** @return array<string, mixed> */
    public function updateVariation(int $parentId, int $variationId, array $payload, string $sku): array;

    /** @return array<string, mixed> */
    public function createVariation(int $parentId, array $payload, string $sku): array;
}

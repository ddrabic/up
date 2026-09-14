<?php

declare(strict_types=1);

namespace Upp\WooCommerce;

interface WooCommerceGatewayInterface
{
    public function checkConnection(): void;

    /** @return array{id: int, sku: string, type: string, parent_id: int|null, name?: string, categories?: list<array{id: int}>}|null */
    public function resolveProductBySku(string $sku): ?array;

    /** @param list<string> $skus @return array<string, array<string, mixed>|null> */
    public function resolveProductsBySku(array $skus): array;

    /** @return array<string, mixed> */
    public function getProduct(int $id, string $sku): array;

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

    /**
     * @param list<array{id: int, sku: string, payload: array<string, mixed>}> $updates
     * @return list<array{success: bool, id?: int, httpStatus?: int|null, wooCode?: string|null, message?: string}>
     */
    public function updateProductsBatch(array $updates): array;

    /** @return array<string, mixed> */
    public function updateVariation(int $parentId, int $variationId, array $payload, string $sku): array;

    /** @return array<string, mixed> */
    public function createVariation(int $parentId, array $payload, string $sku): array;
}

<?php

declare(strict_types=1);

namespace Upp\Domain;

final class ProductRecord
{
    public function __construct(
        public readonly int $index,
        public readonly string $sku,
        public readonly string $name,
        public readonly float $regularPrice,
        public readonly float $discount,
        public readonly bool $active,
        public readonly float $totalStock,
        public readonly array $stockByWarehouse,
        public readonly ?string $primaryCategoryCode,
        public readonly ?string $secondaryCategoryCode,
        public readonly ?string $brandCode,
        public readonly ?string $frameSize,
        public readonly ?string $wheelSize,
        public readonly ?string $gender,
        public readonly ?int $parentProductId = null,
    ) {
    }
}

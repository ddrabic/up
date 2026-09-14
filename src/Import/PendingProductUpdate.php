<?php

declare(strict_types=1);

namespace Upp\Import;

use Upp\Domain\ProductRecord;

final class PendingProductUpdate
{
    public function __construct(
        public readonly ProductRecord $record,
        public readonly int $productId,
        public readonly array $payload,
        public readonly array $warnings,
    ) {
    }
}

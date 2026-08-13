<?php

declare(strict_types=1);

namespace Upp\Import;

final class ImportResult implements \JsonSerializable
{
    public function __construct(
        public readonly int $index,
        public readonly string $sku,
        public readonly string $operation,
        public readonly ?int $productId,
        public readonly ?int $httpStatus,
        public readonly string $message,
        public readonly string $processedAt,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'index' => $this->index,
            'sku' => $this->sku,
            'operation' => $this->operation,
            'productId' => $this->productId,
            'httpStatus' => $this->httpStatus,
            'message' => $this->message,
            'processedAt' => $this->processedAt,
        ];
    }
}

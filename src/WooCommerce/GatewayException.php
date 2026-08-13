<?php

declare(strict_types=1);

namespace Upp\WooCommerce;

use RuntimeException;

final class GatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $wooCode = null,
        public readonly int $attempts = 1,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

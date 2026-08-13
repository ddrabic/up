<?php

declare(strict_types=1);

namespace Upp\Mapping;

final class BrandMapper
{
    public function __construct(private readonly array $map)
    {
    }

    public function nameFor(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $name = $this->map[$code] ?? null;
        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }
}

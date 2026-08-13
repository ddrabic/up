<?php

declare(strict_types=1);

namespace Upp\Mapping;

final class CategoryMapper
{
    public function __construct(private readonly array $map)
    {
    }

    public function slugFor(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $slug = $this->map[(string) $code] ?? null;
        return is_string($slug) && $slug !== '' ? $slug : null;
    }
}

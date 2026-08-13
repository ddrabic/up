<?php

declare(strict_types=1);

namespace Upp\Mapping;

use Upp\Domain\ProductRecord;

final class AttributeMapper
{
    public function map(ProductRecord $record, ?string $brandName): array
    {
        $attributes = [];
        $this->append($attributes, 'Veličina okvira', $record->frameSize);
        $this->append($attributes, 'Veličina kotača', $this->wheelSize($record->wheelSize));
        $this->append($attributes, 'Spol', $record->gender === null ? null : ucfirst($record->gender));
        $this->append($attributes, 'Proizvođač', $brandName);
        return $attributes;
    }

    private function append(array &$attributes, string $name, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $attributes[] = [
            'name' => $name,
            'position' => count($attributes),
            'visible' => true,
            'variation' => false,
            'options' => [$value],
        ];
    }

    private function wheelSize(?string $value): ?string
    {
        return match ($value) {
            null => null,
            '26_plus' => '26"+',
            '27_5' => '27.5"',
            '27_5_plus' => '27.5"+',
            default => str_ends_with($value, '"') ? $value : $value . '"',
        };
    }
}

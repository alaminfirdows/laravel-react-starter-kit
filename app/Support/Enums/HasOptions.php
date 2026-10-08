<?php

namespace App\Support\Enums;

use Illuminate\Support\Str;

/**
 * Select options for backed enums: `{ value, label }` pairs for shadcn `<Select items>`.
 */
trait HasOptions
{
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}

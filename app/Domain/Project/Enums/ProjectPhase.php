<?php

namespace App\Domain\Project\Enums;

enum ProjectPhase: string
{
    case Planning = 'planning';
    case Developing = 'developing';
    case Selling = 'selling';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function description(): string
    {
        return match ($this) {
            self::Planning => 'Shaping the idea: problem, customer, validation and company basics.',
            self::Developing => 'Building the product: scope, brand, tech setup and pre-launch.',
            self::Selling => 'Going to market: launch, acquisition, pricing and operations.',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $phase): array => [
            'value' => $phase->value,
            'label' => $phase->label(),
            'description' => $phase->description(),
        ], self::cases());
    }
}

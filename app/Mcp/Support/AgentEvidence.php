<?php

namespace App\Mcp\Support;

use App\Domain\Task\Data\EvidenceData;
use App\Domain\Task\Enums\EvidenceKind;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;

/**
 * Evidence an agent may attach. Files and check results come from the app,
 * so agents cannot fake a passed check.
 */
final class AgentEvidence
{
    public const array KINDS = [EvidenceKind::Url, EvidenceKind::Value, EvidenceKind::Note];

    /**
     * @return array<string, Type>
     */
    public static function properties(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(self::kindValues())->description('url = link to the result, value = a short fact (e.g. a domain), note = free text.')->required(),
            'label' => $schema->string()->description('What this proves, e.g. "Landing page live".')->required(),
            'value' => $schema->string()->description('The URL, value or note text.')->required(),
            'criterion_key' => $schema->string()->description('Completion criterion this satisfies (key from get_action).'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}kind" => ['required', Rule::in(self::kindValues())],
            "{$prefix}label" => ['required', 'string', 'max:255'],
            "{$prefix}value" => ['required', 'string', 'max:5000', Rule::when(fn ($input): bool => data_get($input, "{$prefix}kind") === EvidenceKind::Url->value, ['url:http,https'])],
            "{$prefix}criterion_key" => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Build from input already checked with rules().
     *
     * @param  array<string, mixed>  $item
     */
    public static function toData(array $item): EvidenceData
    {
        return new EvidenceData(
            kind: EvidenceKind::from(self::string($item, 'kind') ?? ''),
            label: self::string($item, 'label') ?? '',
            value: self::string($item, 'value'),
            criterionKey: self::string($item, 'criterion_key'),
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function string(array $item, string $key): ?string
    {
        return is_string($item[$key] ?? null) ? $item[$key] : null;
    }

    /**
     * @return list<string>
     */
    private static function kindValues(): array
    {
        return array_map(fn (EvidenceKind $kind): string => $kind->value, self::KINDS);
    }
}

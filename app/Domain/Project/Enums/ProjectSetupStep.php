<?php

namespace App\Domain\Project\Enums;

use App\Domain\Project\Models\Project;
use Illuminate\Validation\Rule;

enum ProjectSetupStep: string
{
    case Identity = 'identity';
    case Business = 'business';
    case Market = 'market';
    case Goals = 'goals';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function next(): ?self
    {
        $cases = self::cases();
        $index = (int) array_search($this, $cases, true);

        return $cases[$index + 1] ?? null;
    }

    /**
     * Top-level project attributes this step writes.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return array_values(array_unique(array_map(
            fn (string $key): string => explode('.', $key)[0],
            array_keys($this->rules()),
        )));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return match ($this) {
            self::Identity => [
                'name' => ['required', 'string', 'max:120'],
                'one_liner' => ['required', 'string', 'max:140'],
                'description_md' => ['nullable', 'string', 'max:20000'],
                'website_url' => ['nullable', 'url:http,https', 'max:255'],
            ],
            self::Business => [
                'business_model' => ['required', Rule::enum(BusinessModel::class)],
                'stage' => ['required', Rule::enum(Stage::class)],
                'industry' => ['nullable', 'string', 'max:100'],
                'pricing_model' => ['nullable', 'string', 'max:100'],
            ],
            self::Market => [
                'primary_market' => ['required', 'string', 'size:2', 'alpha', 'uppercase'],
                'target_customer' => ['nullable', 'string', 'max:2000'],
                'problem_statement' => ['nullable', 'string', 'max:5000'],
                'solution_summary' => ['nullable', 'string', 'max:5000'],
            ],
            self::Goals => [
                'goals' => ['present', 'array', 'max:10'],
                'goals.*.title' => ['required', 'string', 'max:140'],
                'goals.*.metric' => ['nullable', 'string', 'max:100'],
                'goals.*.target' => ['nullable', 'string', 'max:100'],
                'goals.*.due' => ['nullable', 'date'],
            ],
        };
    }

    public static function firstIncomplete(Project $project): ?self
    {
        return match (true) {
            blank($project->one_liner) => self::Identity,
            blank($project->business_model) || blank($project->stage) => self::Business,
            blank($project->primary_market) => self::Market,
            default => null,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $step): array => ['value' => $step->value, 'label' => $step->label()], self::cases());
    }
}

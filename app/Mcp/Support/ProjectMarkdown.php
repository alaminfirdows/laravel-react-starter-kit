<?php

namespace App\Mcp\Support;

use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use BackedEnum;

/**
 * Compact Markdown project context for MCP (P2 swaps the profile for the cached context snapshot).
 */
class ProjectMarkdown
{
    public const array SECTIONS = ['profile', 'market', 'goals', 'brand', 'progress'];

    /**
     * @param  array<int, string>  $sections
     */
    public function context(Project $project, array $sections = self::SECTIONS): string
    {
        $parts = ["# {$project->name}\nProject `{$project->id}` · phase {$project->phase->value} · {$project->status->value}"];

        foreach (array_intersect(self::SECTIONS, $sections) as $section) {
            $parts[] = match ($section) {
                'profile' => $this->fields('Profile', $project, [
                    'One-liner' => 'one_liner', 'Description' => 'description_md', 'Website' => 'website_url',
                    'Business model' => 'business_model', 'Pricing' => 'pricing_model', 'Stage' => 'stage',
                    'Industry' => 'industry', 'Problem' => 'problem_statement', 'Solution' => 'solution_summary',
                    'Legal entity' => 'legal_entity_status', 'Jurisdiction' => 'jurisdiction', 'Team size' => 'team_size',
                ]),
                'market' => $this->fields('Market', $project, [
                    'Primary market' => 'primary_market', 'Target markets' => 'target_markets',
                    'Target customer' => 'target_customer', 'Languages' => 'languages', 'Currency' => 'currency',
                ]),
                'goals' => $project->goals ? "## Goals\n- ".implode("\n- ", $project->goals) : null,
                'brand' => $project->brand?->voice_md ? "## Brand voice\n{$project->brand->voice_md}" : null,
                'progress' => $this->progress($project),
            };
        }

        return implode("\n\n", array_filter($parts));
    }

    /**
     * @param  array<string, string>  $fields  label => attribute
     */
    private function fields(string $heading, Project $project, array $fields): ?string
    {
        $lines = [];

        foreach ($fields as $label => $attribute) {
            $value = $project->getAttribute($attribute);
            $value = match (true) {
                $value instanceof BackedEnum => (string) $value->value,
                is_array($value) => implode(', ', array_filter($value, is_scalar(...))),
                is_scalar($value) => (string) $value,
                default => '',
            };

            if ($value !== '') {
                $lines[] = "- {$label}: {$value}";
            }
        }

        return $lines === [] ? null : "## {$heading}\n".implode("\n", $lines);
    }

    private function progress(Project $project): string
    {
        $counts = $project->tasks()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $line = collect(TaskStatus::cases())
            ->filter(fn (TaskStatus $status): bool => (int) ($counts[$status->value] ?? 0) > 0)
            ->map(fn (TaskStatus $status): string => "{$status->value} ".(int) $counts[$status->value])
            ->implode(' · ');

        return "## Progress\n{$project->progress_pct}% done".($line !== '' ? " · {$line}" : '');
    }
}

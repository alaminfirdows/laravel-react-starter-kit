<?php

use App\Ai\Support\SkillRepository;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Skills\Skill;

beforeEach(function () {
    $this->directory = storage_path('framework/testing/skills-'.uniqid());

    $write = function (string $name, bool $inApp): void {
        File::ensureDirectoryExists("{$this->directory}/{$name}");
        File::put("{$this->directory}/{$name}/SKILL.md", implode("\n", [
            '---',
            "name: {$name}",
            "description: Helps with {$name}.",
            'metadata:',
            '    in_app_agents: '.($inApp ? 'true' : 'false'),
            '---',
            '',
            "# {$name}",
            '',
            'Do the work.',
        ]));
    };

    $write('icp-writer', true);
    $write('positioning', true);
    $write('plugin-only', false);
});

afterEach(fn () => File::deleteDirectory($this->directory));

test('loads only skills enabled for in-app agents', function () {
    $skills = (new SkillRepository($this->directory))->forAgents();

    expect($skills)->each->toBeInstanceOf(Skill::class)
        ->and(array_map(fn (Skill $skill): string => $skill->name, $skills))->toEqualCanonicalizing(['icp-writer', 'positioning'])
        ->and($skills[0]->instructions)->toContain('Do the work.');
});

test('filters by name and ignores unknown names', function () {
    $skills = (new SkillRepository($this->directory))->forAgents(['positioning', 'plugin-only', 'missing']);

    expect($skills)->toHaveCount(1)
        ->and($skills[0]->name)->toBe('positioning');
});

test('reads the shipped skills folder by default', function () {
    expect(app(SkillRepository::class)->forAgents())->not->toBeEmpty();
});

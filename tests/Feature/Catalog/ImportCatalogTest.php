<?php

use App\Domain\Catalog\Actions\ImportCatalog;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\Skill;
use App\Domain\Project\Enums\ProjectPhase;
use Illuminate\Support\Facades\File;

function catalogFixture(): string
{
    $dir = storage_path('framework/testing/catalog-'.uniqid());
    File::copyDirectory(base_path('tests/Fixtures/catalog'), $dir);

    return $dir;
}

function fixtureSkills(): string
{
    return base_path('tests/Fixtures/skills');
}

test('imports categories, task tree, actions, dependencies and packs', function () {
    $counts = app(ImportCatalog::class)->handle(catalogFixture(), fixtureSkills());

    $root = CatalogTask::where('key', 'plan.interviews')->firstOrFail();
    $run = CatalogTask::where('key', 'plan.interviews.run')->firstOrFail();

    expect($counts)->toMatchArray(['categories' => 1, 'tasks' => 3, 'actions' => 2, 'prompts' => 1, 'packs' => 1])
        ->and($root->children->pluck('key')->all())->toBe(['plan.interviews.recruit', 'plan.interviews.run'])
        ->and($root->children->first()->category_id)->toBe($root->category_id)
        ->and($root->actions->first()->promptTemplate->key)->toBe('generic')
        ->and($run->dependencies->first()->key)->toBe('plan.interviews.recruit')
        ->and(Pack::defaultForPhase(ProjectPhase::Planning)->items)->toHaveCount(1);
});

test('re-import is idempotent and bumps version only on change', function () {
    $dir = catalogFixture();
    app(ImportCatalog::class)->handle($dir, fixtureSkills());
    app(ImportCatalog::class)->handle($dir, fixtureSkills());

    expect(CatalogTask::count())->toBe(3)
        ->and(CatalogTask::where('key', 'plan.interviews')->value('version'))->toBe(1);

    File::put("{$dir}/tasks/sample.yaml", str_replace('Run interviews', 'Run 10 interviews', File::get("{$dir}/tasks/sample.yaml")));
    app(ImportCatalog::class)->handle($dir, fixtureSkills());

    expect(CatalogTask::where('key', 'plan.interviews')->value('version'))->toBe(2);
});

test('rejects unknown category with file and key in the message', function () {
    $dir = catalogFixture();
    File::put("{$dir}/tasks/bad.yaml", "tasks:\n  - {key: x.bad, category: nope, title: Bad}\n");

    app(ImportCatalog::class)->handle($dir, fixtureSkills());
})->throws(InvalidArgumentException::class, 'bad.yaml: task [x.bad] has unknown category [nope]');

test('rejects tasks deeper than three levels', function () {
    $dir = catalogFixture();
    File::put("{$dir}/tasks/deep.yaml", <<<'YAML'
    tasks:
      - key: d.1
        category: validation
        title: L1
        children:
          - key: d.2
            title: L2
            children:
              - key: d.3
                title: L3
                children:
                  - {key: d.4, title: L4}
    YAML);

    app(ImportCatalog::class)->handle($dir, fixtureSkills());
})->throws(InvalidArgumentException::class, 'task [d.4] is deeper than 3 levels');

test('imports skills from SKILL.md frontmatter and links skills and resources to tasks', function () {
    $dir = catalogFixture();
    $counts = app(ImportCatalog::class)->handle($dir, fixtureSkills());
    app(ImportCatalog::class)->handle($dir, fixtureSkills());

    $task = CatalogTask::where('key', 'plan.interviews')->firstOrFail();

    expect($counts)->toMatchArray(['skills' => 1, 'resources' => 1])
        ->and(Skill::count())->toBe(1)
        ->and(Skill::first())->key->toBe('demo-skill')->version->toBe('2.1.0')->title->toBe('Demo Skill')
        ->and($task->skills->pluck('key')->all())->toBe(['demo-skill'])
        ->and($task->skills->first()->pivot->required)->toBeTrue()
        ->and($task->resources->first())->key->toBe('mom-test')
        ->and($task->resources->first()->pivot->note)->toBe('Read first');
});

test('rejects a task that names an unknown skill', function () {
    app(ImportCatalog::class)->handle(catalogFixture());
})->throws(InvalidArgumentException::class, 'sample.yaml: task [plan.interviews] has unknown skill [demo-skill]');

test('rejects a skill whose name differs from its folder', function () {
    $skills = storage_path('framework/testing/skills-'.uniqid());
    File::ensureDirectoryExists("{$skills}/wrong-folder");
    File::put("{$skills}/wrong-folder/SKILL.md", "---\nname: other-name\ndescription: X\n---\n");

    app(ImportCatalog::class)->handle(catalogFixture(), $skills);
})->throws(InvalidArgumentException::class, 'skills/wrong-folder: name [other-name]');

test('command imports the given path', function () {
    $this->artisan('catalog:import', ['path' => catalogFixture(), '--skills' => fixtureSkills()])
        ->expectsOutputToContain('3 tasks')
        ->assertSuccessful();
});

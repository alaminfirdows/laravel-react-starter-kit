<?php

use Illuminate\Support\Facades\File;

function putFile(string $path, string $contents): void
{
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);
}

beforeEach(function () {
    $this->root = storage_path('framework/testing/plugin-build');
    $this->skills = "{$this->root}/skills";
    $this->output = "{$this->root}/out";

    File::deleteDirectory($this->root);
    putFile("{$this->skills}/pricing-coach/SKILL.md", "---\nname: pricing-coach\ndescription: Helps pick a price.\nmetadata:\n    version: 1.2.0\n---\n\n# Pricing\n");
    putFile("{$this->skills}/pricing-coach/references/tiers.md", 'Tiers');
    putFile("{$this->skills}/internal-only/SKILL.md", "---\nname: internal-only\ndescription: App agents only.\nmetadata:\n    in_plugin: false\n---\nBody\n");
});

afterEach(function () {
    File::deleteDirectory($this->root);
    File::delete(storage_path('app/plugins/founder-os-2.0.0.zip'));
});

test('builds manifest, connector and plugin skills, then zips them', function () {
    $this->artisan('plugin:build', ['--release' => '2.0.0', '--skills' => $this->skills, '--output' => $this->output])
        ->assertSuccessful();

    expect(json_decode(File::get("{$this->output}/.claude-plugin/plugin.json"), true))->toBe([
        'name' => 'founder-os',
        'version' => '2.0.0',
        'description' => 'Work on your Founder OS tasks with Claude: connector plus task skills.',
        'author' => ['name' => config('app.name')],
        'homepage' => config('app.url'),
    ])
        ->and(json_decode(File::get("{$this->output}/.mcp.json"), true))->toBe([
            'mcpServers' => ['founder-os' => ['type' => 'http', 'url' => route('mcp.founder')]],
        ])
        ->and(File::exists("{$this->output}/skills/pricing-coach/references/tiers.md"))->toBeTrue()
        ->and(File::exists("{$this->output}/skills/internal-only"))->toBeFalse();

    $zip = new ZipArchive;
    $zip->open(storage_path('app/plugins/founder-os-2.0.0.zip'));

    expect($zip->locateName('founder-os/.claude-plugin/plugin.json'))->not->toBeFalse()
        ->and($zip->locateName('founder-os/skills/pricing-coach/SKILL.md'))->not->toBeFalse();
});

test('fails on a skill that breaks the authoring rules', function () {
    putFile("{$this->skills}/claude-helper/SKILL.md", "---\nname: claude-helper\ndescription: Bad name.\n---\n");

    $this->artisan('plugin:build', ['--skills' => $this->skills, '--output' => $this->output])
        ->expectsOutputToContain('claude-helper')
        ->assertFailed();

    expect(File::exists($this->output))->toBeFalse();
});

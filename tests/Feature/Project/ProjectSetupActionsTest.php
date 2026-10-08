<?php

use App\Domain\Project\Actions\ActivateProject;
use App\Domain\Project\Actions\UpdateProjectLogo;
use App\Domain\Project\Actions\UpdateProjectSetup;
use App\Domain\Project\Enums\ProjectSetupStep;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->draft()->create();
});

test('a step only writes its own fields', function () {
    app(UpdateProjectSetup::class)->handle($this->project, ProjectSetupStep::Identity, [
        'one_liner' => 'Rockets for cats',
        'stage' => 'scaling',
    ]);

    expect($this->project->fresh()->one_liner)->toBe('Rockets for cats')
        ->and($this->project->fresh()->stage)->toBeNull();
});

test('first incomplete step follows required fields', function () {
    expect(ProjectSetupStep::firstIncomplete($this->project))->toBe(ProjectSetupStep::Identity);

    $this->project->forceFill(['one_liner' => 'x', 'business_model' => 'b2b_saas', 'stage' => 'idea'])->save();

    expect(ProjectSetupStep::firstIncomplete($this->project))->toBe(ProjectSetupStep::Market);
});

test('activation requires the spec fields', function () {
    try {
        app(ActivateProject::class)->handle($this->project);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['one_liner', 'business_model', 'stage', 'primary_market']);
    }

    $this->project->forceFill(['one_liner' => 'x', 'business_model' => 'b2b_saas', 'stage' => 'idea', 'primary_market' => 'DE'])->save();

    expect(app(ActivateProject::class)->handle($this->project)->status)->toBe(ProjectStatus::Active);
});

test('logo upload replaces the previous file', function () {
    Storage::fake('public');

    $first = app(UpdateProjectLogo::class)->handle($this->project, UploadedFile::fake()->image('a.png'));
    $second = app(UpdateProjectLogo::class)->handle($this->project, UploadedFile::fake()->image('b.png'));

    Storage::disk('public')->assertMissing($first->path);
    Storage::disk('public')->assertExists($second->path);
    expect($this->project->fresh()->brand->logo_media_id)->toBe($second->id);
});

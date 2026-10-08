<?php

use App\Ai\Agents\ActionAgent;
use App\Ai\Agents\DraftDocumentAgent;
use App\Ai\Agents\ResearchSummaryAgent;
use App\Ai\Agents\ReviewAgent;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Models\TaskAction;
use Laravel\Ai\Skills\Skill;

test('drafts with the drafting model and returns structured output', function () {
    DraftDocumentAgent::fake([
        ['output_md' => '# ICP', 'outputs' => [['kind' => 'document', 'label' => 'ICP', 'value' => '# ICP', 'doc_type' => 'icp']]],
    ]);

    $response = (new DraftDocumentAgent)->prompt('Draft the ICP.');

    expect($response['output_md'])->toBe('# ICP')
        ->and($response['outputs'][0]['doc_type'])->toBe('icp')
        ->and($response->meta->model)->toBe('claude-opus-5-5');

    DraftDocumentAgent::assertPrompted('Draft the ICP.');
});

test('review and research agents use the default model', function () {
    expect((new ReviewAgent)->model())->toBe('claude-sonnet-5-5')
        ->and((new ResearchSummaryAgent)->model())->toBe('claude-sonnet-5-5');
});

test('loads the named in-app skills', function () {
    $skills = collect((new DraftDocumentAgent(['founder-os-task-runner', 'missing']))->skills());

    expect($skills)->toHaveCount(1)
        ->and($skills->first())->toBeInstanceOf(Skill::class);
});

test('picks the agent from config or action type', function (array $attributes, string $agent) {
    $action = TaskAction::factory()->make($attributes);

    expect(ActionAgent::for($action))->toBeInstanceOf($agent);
})->with([
    'document' => [['type' => ActionType::Document], DraftDocumentAgent::class],
    'research' => [['type' => ActionType::Research], ResearchSummaryAgent::class],
    'check' => [['type' => ActionType::Check], ReviewAgent::class],
    'config wins' => [['type' => ActionType::Document, 'config' => ['agent' => 'review']], ReviewAgent::class],
]);

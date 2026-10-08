<?php

namespace App\Ai\Jobs;

use App\Ai\Agents\ActionAgent;
use App\Ai\Support\UsageMeter;
use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Actions\SaveDocument;
use App\Domain\Knowledge\Data\DocumentData;
use App\Domain\Knowledge\Enums\DocSource;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Prompt\Actions\RenderLauncherPrompt;
use App\Domain\Task\Actions\AttachEvidence;
use App\Domain\Task\Actions\CompleteAction;
use App\Domain\Task\Actions\FinishRun;
use App\Domain\Task\Actions\RequestApproval;
use App\Domain\Task\Actions\SaveRunOutput;
use App\Domain\Task\Data\EvidenceData;
use App\Domain\Task\Enums\EvidenceKind;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Runs one started `app_ai` run: agent → output + usage → approval, completion or back to ready.
 * Unique per action; one try (the founder retries from the UI); `failed()` closes the run.
 */
class RunAppAiActionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public int $uniqueFor;

    public function __construct(public string $runId, public string $userId)
    {
        $this->timeout = (int) config('ai.run_timeout') + 30;
        $this->uniqueFor = $this->timeout;
    }

    public function uniqueId(): string
    {
        return ActionRun::query()->whereKey($this->runId)->value('task_action_id') ?? $this->runId;
    }

    public function handle(UsageMeter $usage, RenderLauncherPrompt $launcher, SaveRunOutput $saveOutput, RequestApproval $requestApproval, CompleteAction $complete, FinishRun $finish): void
    {
        $this->withinWorkspace(function (ActionRun $run, Actor $actor) use ($usage, $launcher, $saveOutput, $requestApproval, $complete, $finish): void {
            $action = $run->action;

            $usage->ensureWithinBudget($run->project->workspace);

            $skills = array_values(array_diff($launcher->skillKeys($action), [RenderLauncherPrompt::RUNNER_SKILL]));
            $response = ActionAgent::for($action, $skills)->prompt((string) $run->rendered_prompt);

            $structured = $response instanceof StructuredAgentResponse ? $response->structured : [];
            $outputMd = (string) ($structured['output_md'] ?? $response->text);
            /** @var list<array{kind?: string, label?: string, value?: string, doc_type?: string}> $outputs */
            $outputs = is_array($structured['outputs'] ?? null) ? array_values($structured['outputs']) : [];

            $saveOutput->handle($action, $actor, $outputMd, ['outputs' => $outputs]);
            $run->update(['usage' => $this->usage($response)]);
            $this->storeOutputs($action, $actor, $outputs);

            if ($action->requires_approval) {
                $requestApproval->handle($action, $actor, Str::limit($outputMd, 2000), ['run_id' => $run->id]);
                $finish->handle($run->refresh(), $actor, RunStatus::Succeeded);

                return;
            }

            try {
                $complete->handle($action->refresh(), $actor);
            } catch (InvalidActionTransition) {
                $finish->handle($run->refresh(), $actor, RunStatus::Succeeded);
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->withinWorkspace(function (ActionRun $run, Actor $actor) use ($exception): void {
            app(FinishRun::class)->handle($run, $actor, RunStatus::Failed, Str::limit($exception?->getMessage() ?? __('The AI run failed.'), 1000));
        });
    }

    /**
     * @param  callable(ActionRun, Actor): void  $callback
     */
    private function withinWorkspace(callable $callback): void
    {
        $run = ActionRun::query()->with('project')->find($this->runId);
        $user = User::query()->find($this->userId);

        if ($run === null || $user === null || $run->status !== RunStatus::Started) {
            return;
        }

        $workspace = Workspace::query()->findOrFail($run->project->workspace_id);

        app(WorkspaceDiscoveryService::class)->runAs($workspace, fn () => $callback($run, Actor::appAi($user)));
    }

    /**
     * @return array{provider: string|null, model: string|null, input_tokens: int, output_tokens: int, total_tokens: int}
     */
    private function usage(AgentResponse $response): array
    {
        return [
            'provider' => $response->meta->provider,
            'model' => $response->meta->model,
            'input_tokens' => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'total_tokens' => $response->usage->totalTokens(),
        ];
    }

    /**
     * Documents become draft knowledge; URLs and values become evidence.
     *
     * @param  list<array{kind?: string, label?: string, value?: string, doc_type?: string}>  $outputs
     */
    private function storeOutputs(TaskAction $action, Actor $actor, array $outputs): void
    {
        foreach ($outputs as $output) {
            $value = trim($output['value'] ?? '');
            $label = Str::limit($output['label'] ?? __('AI output'), 200, '');

            if ($value === '') {
                continue;
            }

            $docType = DocType::tryFrom($output['doc_type'] ?? '');

            match (true) {
                ($output['kind'] ?? null) === 'document' && $docType !== null => app(SaveDocument::class)->handle(
                    $action->task->project,
                    new DocumentData($docType, $label, $value, source: DocSource::AppAi, taskId: $action->task_id, changeNote: __('Drafted by in-app AI')),
                    $actor,
                ),
                in_array($output['kind'] ?? null, ['url', 'value'], true) => app(AttachEvidence::class)->handle(
                    $action,
                    new EvidenceData(EvidenceKind::from((string) $output['kind']), $label, Str::limit($value, 2000, '')),
                    $actor,
                ),
                default => null,
            };
        }
    }
}

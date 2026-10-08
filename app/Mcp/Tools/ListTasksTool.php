<?php

namespace App\Mcp\Tools;

use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Mcp\Support\McpActor;
use App\Mcp\Support\TaskMarkdown;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_tasks')]
#[Description('List tasks of a project in plan order. Filter by status or category, or ask only for tasks that are ready to work on. Paginated: pass next_cursor back as cursor.')]
#[IsReadOnly]
class ListTasksTool extends Tool
{
    public const int DEFAULT_LIMIT = 25;

    public function __construct(private TaskMarkdown $markdown) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'category_key' => ['nullable', 'string', 'max:64'],
            'ready_only' => ['nullable', 'boolean'],
            'cursor' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $project = $mcp->project($validated['project_id']);

        $page = Task::query()
            ->whereBelongsTo($project)
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['category_key'] ?? null, fn (Builder $query, string $key) => $query->where('category_key', $key))
            ->when($validated['ready_only'] ?? false, fn (Builder $query) => $query
                ->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress])
                ->whereDoesntHave('children'))
            ->orderBy('depth')->orderBy('sort_order')->orderBy('id')
            ->cursorPaginate($validated['limit'] ?? self::DEFAULT_LIMIT, ['*'], 'cursor', $validated['cursor'] ?? null);

        $lines = collect($page->items())->map(fn (Task $task): string => $this->markdown->line($task));

        return Response::text(implode("\n\n", array_filter([
            "# Tasks of {$project->name}",
            $lines->isEmpty() ? '_No matching tasks._' : $lines->implode("\n"),
            $page->nextCursor() ? 'next_cursor: '.$page->nextCursor()->encode() : null,
        ])));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ID (ULID).')->required(),
            'status' => $schema->string()->enum(array_column(TaskStatus::cases(), 'value'))->description('Only tasks with this status.'),
            'category_key' => $schema->string()->description('Only tasks in this catalog category, e.g. "legal".'),
            'ready_only' => $schema->boolean()->description('Only leaf tasks that are todo or in progress.'),
            'cursor' => $schema->string()->description('next_cursor from the previous page.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Page size, default 25.'),
        ];
    }
}

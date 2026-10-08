# P0 Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A signed-up user adds a project (phase → wizard), gets a task tree copied from the catalog pack for that phase, opens any task or subtask, reads its Markdown, checks subtasks off, sees progress roll up, and copies a ready prompt for Claude.

**Architecture:** Laravel 13 modules under `app/Domain/{Catalog,Project,Task,Prompt,Activity,Media}`, same shape as the existing `app/Domain/Workspace`. Postgres stores enums as strings with CHECK constraints. Domain Actions own every status change and write the activity log. Inertia v3 + React + shadcn renders a project layout whose sidebar is the task tree.

**Tech Stack:** PHP 8.3+, Laravel 13, PostgreSQL 16, Pest 5, Inertia v3 React, Wayfinder, Tailwind v4, shadcn/ui, Tiptap v3 (`@tiptap/markdown`), react-markdown, symfony/yaml.

**Spec:** `docs/plans/00-overview.md` (decisions), `docs/PROJECT_CONTEXT.md` §4–§7, `docs/DATA_MODEL.md` §B, §C, §E, §F, `docs/CLAUDE.md` rules.

## Global Constraints

- Postgres 16+ for dev and tests; enums = `string` columns + CHECK via `EnumCheck::add()`.
- ULID keys for `projects`, `tasks`, `task_actions`, `media`; bigint for catalog tables and `activity_log`.
- Tenant tables carry `workspace_id` and use `BelongsToWorkspace`.
- Status changes only in `app/Domain/Task/Actions/*` via `forceFill()`; never `update(['status' => …])` elsewhere.
- Every mutation calls `ActivityRecorder::record()`.
- `one_liner` ≤ 140 chars; `summary` ≤ 280 chars; task depth 0–2 (root, subtask, sub-subtask).
- Required to activate a project: `name`, `one_liner`, `stage`, `business_model`, `primary_market`.
- Full prompt output ≤ 14 000 chars.
- Markdown is canonical (`*_md`); rendered client-side with `rehype-sanitize`.
- shadcn files in `resources/js/components/ui` are generated (`pnpm dlx shadcn@latest add …`), never hand-edited.
- Package manager: `pnpm`. Gate: `composer ci:check`.

## Review Focus

1. **Cross-workspace access** — a member of workspace A requesting `/{b}/projects/{slug}` or `/{a}/projects/{slugOfB}/tasks/{idOfB}` gets 404, never data. Pinned in Task 13 (`ProjectIsolationTest`).
2. **Viewer role** — a `viewer` member can open projects and tasks but `POST …/completion` returns 403. Pinned in Task 13.
3. **Re-applying a pack / double submit** — applying the same pack twice must not duplicate tasks. Pinned in Task 8 (`applying twice is idempotent`).
4. **Locked task** — completing a task whose hard dependency is open is refused with a toast, and reopening a dependency re-locks open dependents. Pinned in Task 10.
5. **Prompt template with unknown or missing placeholders / huge body** — unknown `{{ x.y }}` renders empty, output capped at 14 000 chars. Pinned in Task 11.

---

## File Structure

```
app/Support/Database/EnumCheck.php                    CHECK constraints for enum columns
app/Domain/Activity/
  Enums/ActorType.php, ActivityChannel.php
  Data/Actor.php                                      who did it (user|agent|system) + channel
  Models/Activity.php                                 append-only log row
  ActivityRecorder.php                                single write path
app/Domain/Catalog/
  Enums/CatalogPhase.php, CatalogStatus.php
  Models/CatalogCategory.php, CatalogTask.php, CatalogAction.php, PromptTemplate.php, Pack.php, PackItem.php
  Actions/ImportCatalog.php                           YAML → catalog tables (upsert by key)
  Console/ImportCatalogCommand.php                    php artisan catalog:import
  Providers/CatalogServiceProvider.php
app/Domain/Media/Enums/MediaKind.php, Models/Media.php
app/Domain/Project/
  Enums/ProjectPhase.php, ProjectStatus.php, BusinessModel.php, Stage.php, LegalEntityStatus.php, ProjectSetupStep.php
  Models/Project.php, ProjectBrand.php, ProjectPack.php
  Actions/CreateProject.php, ApplyPack.php, UpdateProjectSetup.php, ActivateProject.php, UpdateProjectLogo.php
  Policies/ProjectPolicy.php
  Http/Controllers/ProjectController.php, ProjectSetupController.php, ProjectLogoController.php
  Http/Requests/StoreProjectRequest.php, UpdateProjectSetupRequest.php, UpdateProjectLogoRequest.php
  Data/ProjectPageProps.php                            shared props for project layout pages
app/Domain/Task/
  Enums/TaskStatus.php, TaskPriority.php, ActionType.php, Executor.php, ActionStatus.php, Verification.php, DependencyKind.php
  Exceptions/InvalidTaskTransition.php
  Models/Task.php, TaskAction.php
  Actions/MarkTaskDone.php, ReopenTask.php, RollupTaskStatus.php, RefreshTaskLocks.php
  Queries/ProjectTaskTree.php, TaskDetail.php
  Policies/TaskPolicy.php
  Http/Controllers/TaskController.php, TaskCompletionController.php
app/Domain/Prompt/Actions/RenderFullPrompt.php
database/migrations/2026_10_08_100000_create_activity_log_table.php
database/migrations/2026_10_08_100100_create_catalog_tables.php
database/migrations/2026_10_08_100200_create_projects_tables.php
database/migrations/2026_10_08_100300_create_tasks_tables.php
database/factories/{CatalogCategory,CatalogTask,CatalogAction,PromptTemplate,Pack,Project,Task,TaskAction}Factory.php
database/seeders/catalog/{categories,prompts,packs}.yaml, tasks/*.yaml
database/seeders/CatalogSeeder.php
routes/projects.php                                   required inside the {workspace} group
resources/js/types/project.ts
resources/js/components/markdown/{markdown.tsx, markdown-editor.tsx}
resources/js/components/project/{phase-picker.tsx, project-sidebar.tsx, task-tree.tsx, task-status-icon.tsx, project-progress.tsx, setup-steps.tsx}
resources/js/components/task/{task-header.tsx, parent-task-card.tsx, subtask-list.tsx, action-card.tsx, prompt-buttons.tsx}
resources/js/layouts/project-layout.tsx
resources/js/pages/projects/{index.tsx, create.tsx, setup.tsx, overview.tsx}
resources/js/pages/projects/tasks/show.tsx
tests/Feature/{Activity,Catalog,Project,Task,Prompt}/*Test.php
```

---

### Task 1: Switch to PostgreSQL + EnumCheck helper

**Files:**
- Modify: `.env`, `.env.example` (DB block), `phpunit.xml` (DB env), `.github/workflows/tests.yml` (postgres service)
- Create: `app/Support/Database/EnumCheck.php`
- Test: `tests/Feature/Support/EnumCheckTest.php`

**Interfaces:**
- Produces: `EnumCheck::add(string $table, string $column, class-string<\BackedEnum> $enum, bool $nullable = false): void`, `EnumCheck::drop(string $table, string $column): void`

- [ ] **Step 1: Create local databases**

```bash
createdb founderos && createdb founderos_testing
```

- [ ] **Step 2: Point the app and tests at Postgres**

`.env` and `.env.example`:
```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=founderos
DB_USERNAME=postgres
DB_PASSWORD=
```

`phpunit.xml` — replace the two sqlite lines:
```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="founderos_testing"/>
```

`.github/workflows/tests.yml` — add under `jobs.ci`:
```yaml
    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_PASSWORD: postgres
          POSTGRES_DB: founderos_testing
        ports: ['5432:5432']
        options: >-
          --health-cmd pg_isready --health-interval 10s --health-timeout 5s --health-retries 5
    env:
      DB_CONNECTION: pgsql
      DB_HOST: 127.0.0.1
      DB_USERNAME: postgres
      DB_PASSWORD: postgres
      DB_DATABASE: founderos_testing
```

- [ ] **Step 3: Run the existing suite on Postgres**

Run: `php artisan migrate:fresh && php artisan test`
Expected: all existing tests PASS. If a test fails only on Postgres (ordering, case-sensitive `like`), fix the test or query, not the driver.

- [ ] **Step 4: Write the failing EnumCheck test**

```php
<?php

use App\Domain\Workspace\Enums\WorkspaceType;
use App\Support\Database\EnumCheck;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('enum_probe', function (Blueprint $table) {
        $table->id();
        $table->string('kind', 16)->nullable();
    });
});

test('rejects values outside the enum', function () {
    EnumCheck::add('enum_probe', 'kind', WorkspaceType::class);

    DB::table('enum_probe')->insert(['kind' => 'galaxy']);
})->throws(QueryException::class);

test('accepts enum values and null when nullable', function () {
    EnumCheck::add('enum_probe', 'kind', WorkspaceType::class, nullable: true);

    DB::table('enum_probe')->insert([['kind' => 'personal'], ['kind' => null]]);

    expect(DB::table('enum_probe')->count())->toBe(2);
});

test('drop removes the constraint', function () {
    EnumCheck::add('enum_probe', 'kind', WorkspaceType::class);
    EnumCheck::drop('enum_probe', 'kind');

    DB::table('enum_probe')->insert(['kind' => 'galaxy']);

    expect(DB::table('enum_probe')->count())->toBe(1);
});
```

- [ ] **Step 5: Run to verify it fails**

Run: `php artisan test --filter=EnumCheckTest`
Expected: FAIL — `Class "App\Support\Database\EnumCheck" not found`.

- [ ] **Step 6: Implement**

```php
<?php

namespace App\Support\Database;

use BackedEnum;
use Illuminate\Support\Facades\DB;

/**
 * Enum columns are plain strings plus a CHECK constraint (DATA_MODEL conventions):
 * adding a case later = drop + add, no ALTER TYPE.
 */
final class EnumCheck
{
    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public static function add(string $table, string $column, string $enum, bool $nullable = false): void
    {
        $values = collect($enum::cases())
            ->map(fn (BackedEnum $case): string => DB::getPdo()->quote((string) $case->value))
            ->implode(', ');

        $condition = "{$column} IN ({$values})";

        if ($nullable) {
            $condition = "{$column} IS NULL OR {$condition}";
        }

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT ".self::name($table, $column)." CHECK ({$condition})");
    }

    public static function drop(string $table, string $column): void
    {
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS ".self::name($table, $column));
    }

    private static function name(string $table, string $column): string
    {
        return "{$table}_{$column}_check";
    }
}
```

- [ ] **Step 7: Run tests**

Run: `php artisan test --filter=EnumCheckTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
git add .env.example phpunit.xml .github/workflows/tests.yml app/Support tests/Feature/Support
git commit -m "chore: switch to PostgreSQL and add EnumCheck helper"
```

---

### Task 2: Domain enums

**Files:**
- Create: `app/Domain/Catalog/Enums/{CatalogPhase,CatalogStatus}.php`
- Create: `app/Domain/Project/Enums/{ProjectPhase,ProjectStatus,BusinessModel,Stage,LegalEntityStatus}.php`
- Create: `app/Domain/Task/Enums/{TaskStatus,TaskPriority,ActionType,Executor,ActionStatus,Verification,DependencyKind}.php`
- Create: `app/Domain/Media/Enums/MediaKind.php`
- Test: `tests/Unit/Enums/TaskStatusTest.php`, `tests/Unit/Enums/ProjectPhaseTest.php`

**Interfaces:**
- Produces (values are the exact DB strings):
  - `CatalogPhase`: `pre_planning, research, foundation, product, launch, growth, operations`
  - `CatalogStatus`: `draft, published, archived`
  - `ProjectPhase`: `planning, developing, selling` + `label(): string`, `description(): string`, `static options(): list<array{value,label,description}>`
  - `ProjectStatus`: `draft, active, archived`
  - `BusinessModel`: `b2b_saas, b2c_app, marketplace, agency, ecommerce, other` + `label()`
  - `Stage`: `idea, validating, building, pre_launch, launched, revenue, scaling` + `label()`
  - `LegalEntityStatus`: `none, in_progress, registered`
  - `TaskStatus`: `locked, todo, in_progress, blocked, awaiting_approval, done, skipped` + `isClosed(): bool` (done|skipped), `label(): string`
  - `TaskPriority`: `p0, p1, p2, p3`
  - `ActionType`: `ai, research, browser, document, file, mcp, check, input, approval, manual, wait, scheduled`
  - `Executor`: `claude_desktop, claude_chrome, app_ai, app_system, user`
  - `ActionStatus`: `pending, ready, running, awaiting_input, awaiting_approval, done, failed, skipped` + `isClosed(): bool` (done|skipped)
  - `Verification`: `none, self_reported, evidence_attached, verified`
  - `DependencyKind`: `hard, soft`
  - `MediaKind`: `image, document, export, screenshot`

- [ ] **Step 1: Write failing unit tests**

`tests/Unit/Enums/TaskStatusTest.php`:
```php
<?php

use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\TaskStatus;

test('done and skipped are closed', function (TaskStatus $status, bool $closed) {
    expect($status->isClosed())->toBe($closed);
})->with([
    [TaskStatus::Done, true],
    [TaskStatus::Skipped, true],
    [TaskStatus::Todo, false],
    [TaskStatus::Locked, false],
    [TaskStatus::InProgress, false],
]);

test('action done and skipped are closed', function () {
    expect(ActionStatus::Done->isClosed())->toBeTrue()
        ->and(ActionStatus::Skipped->isClosed())->toBeTrue()
        ->and(ActionStatus::Pending->isClosed())->toBeFalse();
});
```

`tests/Unit/Enums/ProjectPhaseTest.php`:
```php
<?php

use App\Domain\Project\Enums\ProjectPhase;

test('options list every phase with label and description', function () {
    expect(ProjectPhase::options())->toHaveCount(3)
        ->and(ProjectPhase::options()[0])->toBe([
            'value' => 'planning',
            'label' => 'Planning',
            'description' => ProjectPhase::Planning->description(),
        ]);
});
```

- [ ] **Step 2: Run to verify fail**

Run: `php artisan test tests/Unit/Enums`
Expected: FAIL — enum classes not found.

- [ ] **Step 3: Implement the enums**

Pattern for every enum (shown for `TaskStatus`, `ActionStatus`, `ProjectPhase`; the others are plain backed enums with the cases listed in **Interfaces**, plus `label()` returning `Str::headline($this->value)` for `BusinessModel`, `Stage`, `ProjectStatus`):

```php
<?php

namespace App\Domain\Task\Enums;

use Illuminate\Support\Str;

enum TaskStatus: string
{
    case Locked = 'locked';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case AwaitingApproval = 'awaiting_approval';
    case Done = 'done';
    case Skipped = 'skipped';

    public function isClosed(): bool
    {
        return $this === self::Done || $this === self::Skipped;
    }

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
```

```php
<?php

namespace App\Domain\Task\Enums;

enum ActionStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Running = 'running';
    case AwaitingInput = 'awaiting_input';
    case AwaitingApproval = 'awaiting_approval';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isClosed(): bool
    {
        return $this === self::Done || $this === self::Skipped;
    }
}
```

```php
<?php

namespace App\Domain\Project\Enums;

enum ProjectPhase: string
{
    case Planning = 'planning';
    case Developing = 'developing';
    case Selling = 'selling';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function description(): string
    {
        return match ($this) {
            self::Planning => 'Shaping the idea: problem, customer, validation and company basics.',
            self::Developing => 'Building the product: scope, brand, tech setup and pre-launch.',
            self::Selling => 'Going to market: launch, acquisition, pricing and operations.',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $phase): array => [
            'value' => $phase->value,
            'label' => $phase->label(),
            'description' => $phase->description(),
        ], self::cases());
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Unit/Enums`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/*/Enums tests/Unit/Enums
git commit -m "feat: add catalog, project, task and media enums"
```

---

### Task 3: Activity log + ActivityRecorder

**Files:**
- Create: `database/migrations/2026_10_08_100000_create_activity_log_table.php`
- Create: `app/Domain/Activity/Enums/{ActorType,ActivityChannel}.php`, `app/Domain/Activity/Data/Actor.php`, `app/Domain/Activity/Models/Activity.php`, `app/Domain/Activity/ActivityRecorder.php`
- Test: `tests/Feature/Activity/ActivityRecorderTest.php`

**Interfaces:**
- Produces:
  - `ActorType`: `user, agent, system`; `ActivityChannel`: `web, mcp, queue, cli`
  - `Actor::user(User $user, ActivityChannel $channel = ActivityChannel::Web): Actor`, `Actor::system(ActivityChannel $channel = ActivityChannel::Queue): Actor`, `Actor::agent(User $user, string $clientName): Actor`, `Actor::current(): Actor` (auth user → user/web, else system/cli or queue). Public readonly props: `ActorType $type`, `?string $id`, `?string $clientName`, `ActivityChannel $channel`.
  - `ActivityRecorder::record(string $event, Model $subject, array $properties = [], ?Actor $actor = null): Activity` — reads `workspace_id` and `project_id` from the subject (`project_id` = subject id when the subject is a `Project`).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Activity\Enums\ActivityChannel;
use App\Domain\Activity\Enums\ActorType;
use App\Domain\Activity\Models\Activity;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
});

test('records a user action against the subject workspace', function () {
    $activity = app(ActivityRecorder::class)->record(
        'workspace.renamed',
        $this->workspace,
        ['from' => 'A', 'to' => 'B'],
        Actor::user($this->user),
    );

    expect($activity->workspace_id)->toBe($this->workspace->id)
        ->and($activity->actor_type)->toBe(ActorType::User)
        ->and($activity->actor_id)->toBe($this->user->id)
        ->and($activity->channel)->toBe(ActivityChannel::Web)
        ->and($activity->subject_id)->toBe($this->workspace->id)
        ->and($activity->properties)->toBe(['from' => 'A', 'to' => 'B']);
});

test('defaults to the authenticated user', function () {
    $this->actingAs($this->user);

    $activity = app(ActivityRecorder::class)->record('workspace.viewed', $this->workspace);

    expect($activity->actor_id)->toBe($this->user->id);
});

test('activity is scoped to the current workspace', function () {
    $other = Workspace::factory()->create();
    app(ActivityRecorder::class)->record('workspace.viewed', $other, actor: Actor::system());

    expect(Activity::count())->toBe(0)
        ->and(Activity::withoutWorkspaceScope()->count())->toBe(1);
});
```

Note: `Workspace` has `id` = workspace id; `ActivityRecorder` uses `$subject->workspace_id ?? ($subject instanceof Workspace ? $subject->id : null)`.

- [ ] **Step 2: Run to verify fail**

Run: `php artisan test --filter=ActivityRecorderTest`
Expected: FAIL — classes not found.

- [ ] **Step 3: Migration**

```php
<?php

use App\Domain\Activity\Enums\ActivityChannel;
use App\Domain\Activity\Enums\ActorType;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            // No FK: the log outlives soft/hard-deleted projects.
            $table->ulid('project_id')->nullable();
            $table->string('actor_type', 16);
            $table->ulid('actor_id')->nullable();
            $table->string('client_name')->nullable();
            $table->string('channel', 16);
            $table->string('event', 64);
            $table->nullableUlidMorphs('subject');
            $table->jsonb('properties')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
            $table->index(['workspace_id', 'created_at']);
        });

        EnumCheck::add('activity_log', 'actor_type', ActorType::class);
        EnumCheck::add('activity_log', 'channel', ActivityChannel::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
```

- [ ] **Step 4: Implement Actor, Activity, ActivityRecorder**

`app/Domain/Activity/Data/Actor.php`:
```php
<?php

namespace App\Domain\Activity\Data;

use App\Domain\Activity\Enums\ActivityChannel;
use App\Domain\Activity\Enums\ActorType;
use App\Models\User;

final readonly class Actor
{
    public function __construct(
        public ActorType $type,
        public ?string $id,
        public ActivityChannel $channel,
        public ?string $clientName = null,
    ) {}

    public static function user(User $user, ActivityChannel $channel = ActivityChannel::Web): self
    {
        return new self(ActorType::User, $user->id, $channel);
    }

    public static function agent(User $user, string $clientName): self
    {
        return new self(ActorType::Agent, $user->id, ActivityChannel::Mcp, $clientName);
    }

    public static function system(ActivityChannel $channel = ActivityChannel::Queue): self
    {
        return new self(ActorType::System, null, $channel);
    }

    public static function current(): self
    {
        $user = auth()->user();

        if ($user instanceof User) {
            return self::user($user);
        }

        return self::system(app()->runningInConsole() ? ActivityChannel::Cli : ActivityChannel::Queue);
    }
}
```

`app/Domain/Activity/Models/Activity.php`:
```php
<?php

namespace App\Domain\Activity\Models;

use App\Domain\Activity\Enums\ActivityChannel;
use App\Domain\Activity\Enums\ActorType;
use App\Domain\Workspace\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string|null $project_id
 * @property ActorType $actor_type
 * @property string|null $actor_id
 * @property string|null $client_name
 * @property ActivityChannel $channel
 * @property string $event
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $properties
 */
#[Fillable(['workspace_id', 'project_id', 'actor_type', 'actor_id', 'client_name', 'channel', 'event', 'subject_type', 'subject_id', 'properties', 'ip'])]
class Activity extends Model
{
    use BelongsToWorkspace;

    public const null UPDATED_AT = null;

    protected $table = 'activity_log';

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'channel' => ActivityChannel::class,
            'properties' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
```

`app/Domain/Activity/ActivityRecorder.php`:
```php
<?php

namespace App\Domain\Activity;

use App\Domain\Activity\Data\Actor;
use App\Domain\Activity\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

/**
 * The only write path for activity_log (DATA_MODEL §E).
 */
class ActivityRecorder
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(string $event, Model $subject, array $properties = [], ?Actor $actor = null): Activity
    {
        $actor ??= Actor::current();

        return Activity::create([
            'workspace_id' => $subject instanceof Workspace ? $subject->id : $subject->getAttribute('workspace_id'),
            'project_id' => $subject instanceof Project ? $subject->id : $subject->getAttribute('project_id'),
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'client_name' => $actor->clientName,
            'channel' => $actor->channel,
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'properties' => $properties ?: null,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
```

`Project` does not exist yet: the `instanceof Project` check is fine at runtime (class lookup only happens when evaluated against an object, PHP does not autoload for `instanceof`).

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=ActivityRecorderTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/*activity_log* app/Domain/Activity tests/Feature/Activity
git commit -m "feat: add activity log and ActivityRecorder"
```

---

### Task 4: Catalog schema, models, factories

**Files:**
- Create: `database/migrations/2026_10_08_100100_create_catalog_tables.php`
- Create: `app/Domain/Catalog/Models/{CatalogCategory,CatalogTask,CatalogAction,PromptTemplate,Pack,PackItem}.php`
- Create: `database/factories/{CatalogCategory,CatalogTask,CatalogAction,PromptTemplate,Pack}Factory.php`
- Test: `tests/Feature/Catalog/CatalogModelTest.php`

**Interfaces:**
- Produces:
  - `CatalogTask` relations: `category(): BelongsTo`, `parent(): BelongsTo`, `children(): HasMany` (ordered by `sort_order`), `actions(): HasMany` (ordered), `dependencies(): BelongsToMany<CatalogTask>` (pivot `kind`), casts `applicability|completion_criteria|expected_outputs|body_doc` → `array`, `status` → `CatalogStatus`.
  - `CatalogAction`: `task()`, `promptTemplate()`; casts `type` → `ActionType`, `executor` → `Executor`, `config` → `array`.
  - `Pack`: `items(): HasMany<PackItem>` ordered, `static defaultForPhase(ProjectPhase $phase): ?Pack` (published + `is_default` + `audience->phase`).
  - `PackItem`: `pack()`, `catalogTask()`.
  - Factories: `CatalogTaskFactory::childOf(CatalogTask $parent)`, `PackFactory::defaultFor(ProjectPhase $phase)`, `PackFactory::withTasks(CatalogTask ...$tasks)` (include_subtree true).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Enums\ProjectPhase;

test('catalog task tree and actions load in order', function () {
    $root = CatalogTask::factory()->create();
    $second = CatalogTask::factory()->childOf($root)->create(['sort_order' => 2]);
    $first = CatalogTask::factory()->childOf($root)->create(['sort_order' => 1]);

    expect($root->children->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($first->parent->is($root))->toBeTrue();
});

test('default pack is resolved per phase', function () {
    Pack::factory()->defaultFor(ProjectPhase::Selling)->create(['key' => 'gtm']);
    Pack::factory()->defaultFor(ProjectPhase::Planning)->create(['key' => 'plan']);
    Pack::factory()->create(['key' => 'extra', 'audience' => ['phase' => 'planning'], 'is_default' => false]);

    expect(Pack::defaultForPhase(ProjectPhase::Planning)?->key)->toBe('plan')
        ->and(Pack::defaultForPhase(ProjectPhase::Developing))->toBeNull();
});
```

- [ ] **Step 2: Run to verify fail**

Run: `php artisan test --filter=CatalogModelTest`
Expected: FAIL — classes not found.

- [ ] **Step 3: Migration**

```php
<?php

use App\Domain\Catalog\Enums\CatalogPhase;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\DependencyKind;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Enums\TaskPriority;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description_md')->nullable();
            $table->string('phase', 32);
            $table->string('icon', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        EnumCheck::add('catalog_categories', 'phase', CatalogPhase::class);

        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->text('launcher_md')->nullable();
            $table->text('full_md');
            $table->jsonb('variables')->nullable();
            $table->string('target', 16)->default('chat');
            $table->jsonb('skill_keys')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('content_hash', 64);
            $table->timestamps();
        });

        Schema::create('catalog_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('category_id')->constrained('catalog_categories')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('catalog_tasks')->cascadeOnDelete();
            $table->string('title');
            $table->string('summary', 280)->nullable();
            $table->text('body_md')->nullable();
            $table->jsonb('body_doc')->nullable();
            $table->jsonb('applicability')->nullable();
            $table->string('priority_default', 2)->default('p2');
            $table->unsignedInteger('est_minutes')->nullable();
            $table->unsignedSmallInteger('difficulty')->nullable();
            $table->boolean('is_optional')->default(false);
            $table->jsonb('completion_criteria')->nullable();
            $table->jsonb('expected_outputs')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('content_hash', 64);
            $table->string('status', 16)->default('published');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });
        EnumCheck::add('catalog_tasks', 'priority_default', TaskPriority::class);
        EnumCheck::add('catalog_tasks', 'status', CatalogStatus::class);

        Schema::create('catalog_task_dependencies', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('catalog_tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_id')->constrained('catalog_tasks')->cascadeOnDelete();
            $table->string('kind', 8)->default('hard');
            $table->primary(['task_id', 'depends_on_id']);
        });
        EnumCheck::add('catalog_task_dependencies', 'kind', DependencyKind::class);

        Schema::create('catalog_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_task_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('title');
            $table->string('type', 16);
            $table->string('executor', 16);
            $table->text('instructions_md')->nullable();
            $table->foreignId('prompt_template_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('config')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('requires_approval')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['catalog_task_id', 'key']);
        });
        EnumCheck::add('catalog_actions', 'type', ActionType::class);
        EnumCheck::add('catalog_actions', 'executor', Executor::class);

        Schema::create('packs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description_md')->nullable();
            $table->jsonb('audience')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 16)->default('published');
            $table->timestamps();
        });
        EnumCheck::add('packs', 'status', CatalogStatus::class);

        Schema::create('pack_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_task_id')->constrained()->cascadeOnDelete();
            $table->boolean('include_subtree')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['pack_id', 'catalog_task_id']);
        });
    }

    public function down(): void
    {
        foreach (['pack_items', 'packs', 'catalog_actions', 'catalog_task_dependencies', 'catalog_tasks', 'prompt_templates', 'catalog_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
```

`content_hash` is an addition to DATA_MODEL §B: sha256 of the authored content, used by the importer to bump `version` only on real change. Task 19 adds it to the spec.

- [ ] **Step 4: Models** (namespace `App\Domain\Catalog\Models`, `#[UseFactory]` per model, `@property` docblocks like `Workspace`)

`CatalogTask.php` (core of the module):
```php
<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Task\Enums\TaskPriority;
use Database\Factories\CatalogTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property int $category_id
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $summary
 * @property string|null $body_md
 * @property TaskPriority $priority_default
 * @property array<int, array<string, mixed>>|null $completion_criteria
 * @property array<int, array<string, mixed>>|null $expected_outputs
 * @property int $version
 * @property CatalogStatus $status
 * @property int $sort_order
 * @property-read CatalogCategory $category
 */
#[Fillable(['key', 'category_id', 'parent_id', 'title', 'summary', 'body_md', 'body_doc', 'applicability', 'priority_default', 'est_minutes', 'difficulty', 'is_optional', 'completion_criteria', 'expected_outputs', 'version', 'content_hash', 'status', 'published_at', 'sort_order'])]
#[UseFactory(CatalogTaskFactory::class)]
class CatalogTask extends Model
{
    /** @use HasFactory<CatalogTaskFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'body_doc' => 'array',
            'applicability' => 'array',
            'completion_criteria' => 'array',
            'expected_outputs' => 'array',
            'is_optional' => 'boolean',
            'priority_default' => TaskPriority::class,
            'status' => CatalogStatus::class,
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<CatalogCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class);
    }

    /** @return BelongsTo<CatalogTask, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<CatalogTask, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<CatalogAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(CatalogAction::class)->orderBy('sort_order');
    }

    /** @return BelongsToMany<CatalogTask, $this> */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'catalog_task_dependencies', 'task_id', 'depends_on_id')
            ->withPivot('kind');
    }
}
```

`Pack.php` key method:
```php
public static function defaultForPhase(ProjectPhase $phase): ?self
{
    return static::query()
        ->where('status', CatalogStatus::Published)
        ->where('is_default', true)
        ->where('audience->phase', $phase->value)
        ->orderByDesc('version')
        ->first();
}

/** @return HasMany<PackItem, $this> */
public function items(): HasMany
{
    return $this->hasMany(PackItem::class)->orderBy('sort_order');
}
```
`Pack` casts: `audience` → `array`, `is_default` → `boolean`, `status` → `CatalogStatus`. `PackItem`: `public $timestamps = false;`, casts `include_subtree` → `boolean`. `CatalogCategory` casts `phase` → `CatalogPhase`. `CatalogAction` casts listed in Interfaces. `PromptTemplate` casts `variables`, `skill_keys` → `array`.

- [ ] **Step 5: Factories**

`CatalogTaskFactory`:
```php
public function definition(): array
{
    $title = fake()->unique()->sentence(3);

    return [
        'key' => 'test.'.Str::slug($title),
        'category_id' => CatalogCategory::factory(),
        'title' => $title,
        'summary' => fake()->sentence(),
        'body_md' => "## Why\n\n".fake()->paragraph(),
        'priority_default' => TaskPriority::P2,
        'version' => 1,
        'content_hash' => hash('sha256', $title),
        'status' => CatalogStatus::Published,
        'published_at' => now(),
        'sort_order' => 0,
    ];
}

public function childOf(CatalogTask $parent): static
{
    return $this->state(['parent_id' => $parent->id, 'category_id' => $parent->category_id]);
}
```

`PackFactory`:
```php
public function definition(): array
{
    return [
        'key' => 'pack-'.fake()->unique()->slug(2),
        'name' => fake()->words(3, true),
        'audience' => ['phase' => ProjectPhase::Planning->value],
        'is_default' => false,
        'version' => 1,
        'status' => CatalogStatus::Published,
    ];
}

public function defaultFor(ProjectPhase $phase): static
{
    return $this->state(['audience' => ['phase' => $phase->value], 'is_default' => true]);
}

public function withTasks(CatalogTask ...$tasks): static
{
    return $this->afterCreating(function (Pack $pack) use ($tasks): void {
        foreach (array_values($tasks) as $i => $task) {
            $pack->items()->create(['catalog_task_id' => $task->id, 'include_subtree' => true, 'sort_order' => $i]);
        }
    });
}
```

`CatalogCategoryFactory`: `key` unique slug, `name`, `phase` = `CatalogPhase::Foundation`. `CatalogActionFactory`: `catalog_task_id` = factory, `key` unique slug, `title`, `type` = `ActionType::Manual`, `executor` = `Executor::User`, `instructions_md`, `is_required` true. `PromptTemplateFactory`: `key`, `title`, `full_md` = `"Help {{ project.name }} with {{ task.title }}."`, `content_hash`.

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=CatalogModelTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/*catalog* app/Domain/Catalog/Models database/factories tests/Feature/Catalog
git commit -m "feat: add catalog schema, models and factories"
```

---

### Task 5: Catalog YAML importer, command and sample content

**Files:**
- Create: `app/Domain/Catalog/Actions/ImportCatalog.php`, `app/Domain/Catalog/Console/ImportCatalogCommand.php`, `app/Domain/Catalog/Providers/CatalogServiceProvider.php`
- Modify: `bootstrap/providers.php` (add `CatalogServiceProvider`), `composer.json` (`symfony/yaml`), `database/seeders/DatabaseSeeder.php` (call `CatalogSeeder`)
- Create: `database/seeders/CatalogSeeder.php`, `database/seeders/catalog/{categories,prompts,packs}.yaml`, `database/seeders/catalog/tasks/{planning,developing,selling}.yaml`
- Test: `tests/Feature/Catalog/ImportCatalogTest.php`, fixtures `tests/Fixtures/catalog/{categories,prompts,packs}.yaml`, `tests/Fixtures/catalog/tasks/sample.yaml`

**Interfaces:**
- Consumes: Task 4 models.
- Produces: `ImportCatalog::handle(string $directory): array{categories:int, tasks:int, actions:int, prompts:int, packs:int}`; throws `InvalidArgumentException` naming the file + key on: unknown category, unknown prompt key, unknown dependency key, depth > 2, two default packs for one phase. Command `catalog:import {path=database/seeders/catalog}`.

YAML format (authoring contract — goes into `database/seeders/catalog/README.md`):

```yaml
# categories.yaml
categories:
  - key: idea-validation
    name: Idea validation
    phase: research            # CatalogPhase
    icon: lightbulb            # lucide icon name
    sort_order: 1
    description_md: Prove people have the problem.

# prompts.yaml
prompts:
  - key: generic-task
    title: Generic task prompt
    target: chat               # chat|cowork|code
    full_md: |
      You are helping {{ project.name }} — {{ project.one_liner }}.
      Task: {{ task.title }}
      {{ task.body_md }}
      Step: {{ action.title }}
      {{ action.instructions_md }}

# tasks/planning.yaml
tasks:
  - key: planning.problem-interviews
    category: idea-validation
    title: Run 10 problem interviews
    summary: Talk to people who have the problem before building.
    priority: p1
    est_minutes: 600
    body_md: |
      ## Why
      ...
    depends_on: []             # [{key: other.task, kind: hard}]
    actions:
      - key: write-script
        title: Draft an interview script
        type: ai
        executor: claude_desktop
        prompt: generic-task
        instructions_md: Draft 8 open questions about the problem.
    children:
      - key: planning.problem-interviews.recruit
        title: Recruit 10 interviewees
        actions: [{key: recruit, title: Book 10 calls, type: manual, executor: user}]

# packs.yaml
packs:
  - key: planning-starter
    name: Planning starter
    phase: planning            # ProjectPhase → audience.phase
    is_default: true
    items:
      - task: planning.problem-interviews     # include_subtree defaults to true
```

Children inherit `category` from the parent.

- [ ] **Step 1: Add the YAML dependency**

Run: `composer require symfony/yaml`

- [ ] **Step 2: Write test fixtures**

`tests/Fixtures/catalog/categories.yaml`:
```yaml
categories:
  - {key: validation, name: Validation, phase: research, sort_order: 1}
```
`tests/Fixtures/catalog/prompts.yaml`:
```yaml
prompts:
  - key: generic
    title: Generic
    full_md: "Help {{ project.name }} with {{ task.title }}."
```
`tests/Fixtures/catalog/tasks/sample.yaml`:
```yaml
tasks:
  - key: plan.interviews
    category: validation
    title: Run interviews
    priority: p1
    actions:
      - {key: script, title: Draft script, type: ai, executor: claude_desktop, prompt: generic}
    children:
      - key: plan.interviews.recruit
        title: Recruit people
        actions: [{key: recruit, title: Book calls, type: manual, executor: user}]
      - key: plan.interviews.run
        title: Hold the calls
        depends_on: [{key: plan.interviews.recruit, kind: hard}]
```
`tests/Fixtures/catalog/packs.yaml`:
```yaml
packs:
  - key: planning-starter
    name: Planning starter
    phase: planning
    is_default: true
    items: [{task: plan.interviews}]
```

- [ ] **Step 3: Write the failing test**

```php
<?php

use App\Domain\Catalog\Actions\ImportCatalog;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Enums\ProjectPhase;
use Illuminate\Support\Facades\File;

function catalogFixture(): string
{
    $dir = storage_path('framework/testing/catalog-'.uniqid());
    File::copyDirectory(base_path('tests/Fixtures/catalog'), $dir);

    return $dir;
}

test('imports categories, task tree, actions, dependencies and packs', function () {
    $counts = app(ImportCatalog::class)->handle(catalogFixture());

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
    app(ImportCatalog::class)->handle($dir);
    app(ImportCatalog::class)->handle($dir);

    expect(CatalogTask::count())->toBe(3)
        ->and(CatalogTask::where('key', 'plan.interviews')->value('version'))->toBe(1);

    File::put("{$dir}/tasks/sample.yaml", str_replace('Run interviews', 'Run 10 interviews', File::get("{$dir}/tasks/sample.yaml")));
    app(ImportCatalog::class)->handle($dir);

    expect(CatalogTask::where('key', 'plan.interviews')->value('version'))->toBe(2);
});

test('rejects unknown category with file and key in the message', function () {
    $dir = catalogFixture();
    File::put("{$dir}/tasks/bad.yaml", "tasks:\n  - {key: x.bad, category: nope, title: Bad}\n");

    app(ImportCatalog::class)->handle($dir);
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

    app(ImportCatalog::class)->handle($dir);
})->throws(InvalidArgumentException::class, 'task [d.4] is deeper than 3 levels');

test('command imports the given path', function () {
    $this->artisan('catalog:import', ['path' => catalogFixture()])
        ->expectsOutputToContain('3 tasks')
        ->assertSuccessful();
});
```

- [ ] **Step 4: Run to verify fail**

Run: `php artisan test --filter=ImportCatalogTest`
Expected: FAIL — `ImportCatalog` not found.

- [ ] **Step 5: Implement ImportCatalog**

```php
<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PromptTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Upserts the YAML catalog by stable keys. Catalog is immutable at runtime
 * (CLAUDE.md rule 7): this is the only writer.
 */
class ImportCatalog
{
    public const int MAX_DEPTH = 2;

    /** @var array{categories:int, tasks:int, actions:int, prompts:int, packs:int} */
    private array $counts;

    /** @var array<string, int> */
    private array $categoryIds = [];

    /** @var array<string, int> */
    private array $promptIds = [];

    /** @var array<string, int> */
    private array $taskIds = [];

    /** @var list<array{file:string, task:string, depends_on:string, kind:string}> */
    private array $pendingDependencies = [];

    /**
     * @return array{categories:int, tasks:int, actions:int, prompts:int, packs:int}
     */
    public function handle(string $directory): array
    {
        $this->counts = ['categories' => 0, 'tasks' => 0, 'actions' => 0, 'prompts' => 0, 'packs' => 0];

        DB::transaction(function () use ($directory): void {
            $this->importCategories($this->read("{$directory}/categories.yaml"));
            $this->importPrompts($this->read("{$directory}/prompts.yaml"));

            foreach (File::glob("{$directory}/tasks/*.yaml") as $file) {
                foreach ($this->read($file)['tasks'] ?? [] as $i => $task) {
                    $this->importTask(basename($file), $task, null, 0, $i);
                }
            }

            $this->importDependencies();
            $this->importPacks($this->read("{$directory}/packs.yaml"));
        });

        return $this->counts;
    }

    /** @return array<string, mixed> */
    private function read(string $file): array
    {
        return File::exists($file) ? (array) Yaml::parseFile($file) : [];
    }

    /** @param array<string, mixed> $data */
    private function importCategories(array $data): void
    {
        foreach ($data['categories'] ?? [] as $i => $row) {
            $category = CatalogCategory::updateOrCreate(['key' => $row['key']], [
                'name' => $row['name'],
                'phase' => $row['phase'],
                'icon' => $row['icon'] ?? null,
                'description_md' => $row['description_md'] ?? null,
                'sort_order' => $row['sort_order'] ?? $i,
            ]);
            $this->categoryIds[$category->key] = $category->id;
            $this->counts['categories']++;
        }
    }

    /** @param array<string, mixed> $data */
    private function importPrompts(array $data): void
    {
        foreach ($data['prompts'] ?? [] as $row) {
            $attributes = [
                'title' => $row['title'],
                'launcher_md' => $row['launcher_md'] ?? null,
                'full_md' => $row['full_md'],
                'variables' => $row['variables'] ?? null,
                'target' => $row['target'] ?? 'chat',
                'skill_keys' => $row['skills'] ?? null,
            ];
            $prompt = $this->upsertVersioned(PromptTemplate::class, $row['key'], $attributes);
            $this->promptIds[$prompt->key] = $prompt->id;
            $this->counts['prompts']++;
        }
    }

    /** @param array<string, mixed> $row */
    private function importTask(string $file, array $row, ?CatalogTask $parent, int $depth, int $position): void
    {
        $key = $row['key'];

        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException("{$file}: task [{$key}] is deeper than 3 levels");
        }

        $categoryId = $parent?->category_id ?? $this->categoryIds[$row['category'] ?? ''] ?? null;

        if ($categoryId === null) {
            $category = $row['category'] ?? '';
            throw new InvalidArgumentException("{$file}: task [{$key}] has unknown category [{$category}]");
        }

        $task = $this->upsertVersioned(CatalogTask::class, $key, [
            'category_id' => $categoryId,
            'parent_id' => $parent?->id,
            'title' => $row['title'],
            'summary' => $row['summary'] ?? null,
            'body_md' => $row['body_md'] ?? null,
            'applicability' => $row['applicability'] ?? null,
            'priority_default' => $row['priority'] ?? 'p2',
            'est_minutes' => $row['est_minutes'] ?? null,
            'difficulty' => $row['difficulty'] ?? null,
            'is_optional' => $row['optional'] ?? false,
            'completion_criteria' => $row['completion_criteria'] ?? null,
            'expected_outputs' => $row['expected_outputs'] ?? null,
            'status' => $row['status'] ?? CatalogStatus::Published->value,
            'sort_order' => $position,
            'actions' => $row['actions'] ?? [],
        ], except: ['actions']);

        $task->published_at ??= now();
        $task->save();

        $this->taskIds[$key] = $task->id;
        $this->counts['tasks']++;

        $this->importActions($file, $task, $row['actions'] ?? []);

        foreach ($row['depends_on'] ?? [] as $dependency) {
            $this->pendingDependencies[] = ['file' => $file, 'task' => $key, 'depends_on' => $dependency['key'], 'kind' => $dependency['kind'] ?? 'hard'];
        }

        foreach ($row['children'] ?? [] as $i => $child) {
            $this->importTask($file, $child, $task, $depth + 1, $i);
        }
    }

    /** @param list<array<string, mixed>> $actions */
    private function importActions(string $file, CatalogTask $task, array $actions): void
    {
        $keys = [];

        foreach ($actions as $i => $row) {
            $promptId = null;

            if (isset($row['prompt'])) {
                $promptId = $this->promptIds[$row['prompt']]
                    ?? throw new InvalidArgumentException("{$file}: action [{$task->key}.{$row['key']}] has unknown prompt [{$row['prompt']}]");
            }

            $task->actions()->updateOrCreate(['key' => $row['key']], [
                'title' => $row['title'],
                'type' => $row['type'],
                'executor' => $row['executor'],
                'instructions_md' => $row['instructions_md'] ?? null,
                'prompt_template_id' => $promptId,
                'config' => $row['config'] ?? null,
                'is_required' => $row['required'] ?? true,
                'requires_approval' => $row['requires_approval'] ?? false,
                'sort_order' => $i,
                'version' => $task->version,
            ]);
            $keys[] = $row['key'];
            $this->counts['actions']++;
        }

        $task->actions()->whereNotIn('key', $keys)->delete();
    }

    private function importDependencies(): void
    {
        foreach ($this->pendingDependencies as $dep) {
            $dependsOn = $this->taskIds[$dep['depends_on']]
                ?? throw new InvalidArgumentException("{$dep['file']}: task [{$dep['task']}] depends on unknown task [{$dep['depends_on']}]");

            DB::table('catalog_task_dependencies')->updateOrInsert(
                ['task_id' => $this->taskIds[$dep['task']], 'depends_on_id' => $dependsOn],
                ['kind' => $dep['kind']],
            );
        }
    }

    /** @param array<string, mixed> $data */
    private function importPacks(array $data): void
    {
        $defaults = [];

        foreach ($data['packs'] ?? [] as $row) {
            if (($row['is_default'] ?? false) && isset($defaults[$row['phase']])) {
                throw new InvalidArgumentException("packs.yaml: phase [{$row['phase']}] has two default packs [{$defaults[$row['phase']]}, {$row['key']}]");
            }

            if ($row['is_default'] ?? false) {
                $defaults[$row['phase']] = $row['key'];
            }

            $pack = $this->upsertVersioned(Pack::class, $row['key'], [
                'name' => $row['name'],
                'description_md' => $row['description_md'] ?? null,
                'audience' => ['phase' => $row['phase'], ...($row['audience'] ?? [])],
                'is_default' => $row['is_default'] ?? false,
                'status' => $row['status'] ?? CatalogStatus::Published->value,
                'items' => $row['items'],
            ], except: ['items']);

            $pack->items()->delete();

            foreach ($row['items'] as $i => $item) {
                $pack->items()->create([
                    'catalog_task_id' => $this->taskIds[$item['task']]
                        ?? CatalogTask::where('key', $item['task'])->value('id')
                        ?? throw new InvalidArgumentException("packs.yaml: pack [{$row['key']}] has unknown task [{$item['task']}]"),
                    'include_subtree' => $item['include_subtree'] ?? true,
                    'sort_order' => $i,
                ]);
            }

            $this->counts['packs']++;
        }
    }

    /**
     * Upsert by key; bump version when the authored content hash changes.
     *
     * @template TModel of CatalogTask|PromptTemplate|Pack
     *
     * @param  class-string<TModel>  $model
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $except  hashed but not stored
     * @return TModel
     */
    private function upsertVersioned(string $model, string $key, array $attributes, array $except = []): CatalogTask|PromptTemplate|Pack
    {
        $hash = hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
        $stored = array_diff_key($attributes, array_flip($except));
        $existing = $model::query()->where('key', $key)->first();

        if ($existing === null) {
            return $model::create(['key' => $key, ...$stored, 'version' => 1, 'content_hash' => $hash]);
        }

        if ($existing->content_hash !== $hash) {
            $existing->fill([...$stored, 'content_hash' => $hash, 'version' => $existing->version + 1])->save();
        }

        return $existing;
    }
}
```

`packs` needs a `content_hash` column: add `$table->string('content_hash', 64)->nullable();` to the `packs` create in Task 4's migration (not yet run on production, edit in place) and to `Pack`'s `#[Fillable]`.

- [ ] **Step 6: Command + provider**

```php
<?php

namespace App\Domain\Catalog\Console;

use App\Domain\Catalog\Actions\ImportCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:import {path=database/seeders/catalog : Directory with categories.yaml, prompts.yaml, packs.yaml and tasks/*.yaml}')]
#[Description('Import the founder catalog from YAML')]
class ImportCatalogCommand extends Command
{
    public function handle(ImportCatalog $import): int
    {
        $path = (string) $this->argument('path');
        $counts = $import->handle(str_starts_with($path, '/') ? $path : base_path($path));

        $this->components->info(sprintf(
            'Imported %d categories, %d tasks, %d actions, %d prompts, %d packs.',
            ...array_values($counts),
        ));

        return self::SUCCESS;
    }
}
```

(If `#[Signature]`/`#[Description]` attributes are not available in the installed framework, use `protected $signature` / `protected $description` — check with `search-docs`.)

`CatalogServiceProvider::boot()` registers the command when `runningInConsole()` (same as `WorkspaceServiceProvider`). Add to `bootstrap/providers.php`.

`database/seeders/CatalogSeeder.php`:
```php
public function run(ImportCatalog $import): void
{
    $import->handle(database_path('seeders/catalog'));
}
```
Call it first in `DatabaseSeeder::run()`.

- [ ] **Step 7: Write sample catalog content**

Create `database/seeders/catalog/` files in the format above with: 6 categories (2 per phase), 1 prompt `generic-task` (full text shown in the format block, ending with the completion protocol from PROJECT_CONTEXT §8), and per phase 2 root tasks × 2–3 subtasks, at least one `depends_on` hard link, at least one action per leaf (`manual`/`user` for plain checks, `ai`/`claude_desktop` + `prompt: generic-task` for writing work), and one default pack per phase (`planning-starter`, `developing-starter`, `selling-starter`). Add `database/seeders/catalog/README.md` with the YAML format block above.

Sample root tasks: planning → "Define the problem and customer", "Run 10 problem interviews"; developing → "Lock the MVP scope", "Set up brand basics"; selling → "Prepare launch", "Set up pricing and payments".

- [ ] **Step 8: Run tests + import the real files**

Run: `php artisan test --filter=ImportCatalogTest && php artisan catalog:import`
Expected: tests PASS; command prints `Imported 6 categories, … 3 packs.`

- [ ] **Step 9: Commit**

```bash
git add composer.json composer.lock bootstrap/providers.php app/Domain/Catalog database/seeders tests/Feature/Catalog tests/Fixtures/catalog database/migrations/*catalog*
git commit -m "feat: import catalog from YAML with sample packs per phase"
```

---

### Task 6: Projects schema, Media, models, policy

**Files:**
- Create: `database/migrations/2026_10_08_100200_create_projects_tables.php`
- Create: `app/Domain/Project/Models/{Project,ProjectBrand,ProjectPack}.php`, `app/Domain/Media/Models/Media.php`, `app/Domain/Project/Policies/ProjectPolicy.php`
- Create: `database/factories/ProjectFactory.php`
- Modify: `app/Domain/Workspace/Models/Workspace.php` (add `projects(): HasMany`)
- Test: `tests/Feature/Project/ProjectModelTest.php`, `tests/Feature/Project/ProjectPolicyTest.php`

**Interfaces:**
- Produces:
  - `Project` (ULID, `BelongsToWorkspace`, `SoftDeletes`, route key `slug`): relations `owner()`, `brand(): HasOne<ProjectBrand>`, `tasks(): HasMany<Task>`, `packs(): HasMany<ProjectPack>`; casts `phase` → `ProjectPhase`, `status` → `ProjectStatus`, `business_model` → `BusinessModel`, `stage` → `Stage`, `legal_entity_status` → `LegalEntityStatus`, `target_markets|languages|goals|tech|settings|meta|description_doc` → `array`, `founded_on` → `immutable_date`, `activated_at` → `immutable_datetime`; accessor `logo_url` (via `brand.logo`); `isDraft(): bool`.
  - Fillable (user-editable profile only): `name, one_liner, description_md, description_doc, website_url, primary_domain, business_model, industry, stage, pricing_model, revenue_band, primary_market, target_markets, languages, target_customer, problem_statement, solution_summary, legal_entity_status, entity_type, jurisdiction, founded_on, team_size, timezone, currency, goals, tech`. `workspace_id, owner_id, slug, phase, status, activated_at` set via `forceFill` in Actions.
  - `ProjectFactory`: default `phase=planning`, `status=active`, required profile filled; states `draft()`, `forWorkspace(Workspace $w)` (sets `workspace_id` + `owner_id` = owner).
  - `ProjectPolicy`: `viewAny(User)`, `view(User, Project)`, `create(User)` (role ≥ Member in current workspace), `update(User, Project)` (role ≥ Member), `delete(User, Project)` (role ≥ Admin or project owner).
  - `Workspace::projects(): HasMany<Project>` — needed by `scopeBindings()`.

- [ ] **Step 1: Write failing tests**

`ProjectModelTest.php`:
```php
<?php

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
});

test('project is scoped to the current workspace', function () {
    Project::factory()->forWorkspace($this->workspace)->create();
    Project::factory()->forWorkspace(Workspace::factory()->create())->create();

    expect(Project::count())->toBe(1);
});

test('slug is unique per workspace only', function () {
    Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'acme']);
    Project::factory()->forWorkspace(Workspace::factory()->create())->create(['slug' => 'acme']);

    Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'acme']);
})->throws(QueryException::class);

test('invalid phase is rejected by the database', function () {
    Project::factory()->forWorkspace($this->workspace)->create()->forceFill(['phase' => 'dreaming'])->save();
})->throws(QueryException::class);
```

`ProjectPolicyTest.php`:
```php
<?php

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

test('access by role', function (WorkspaceRole $role, bool $view, bool $update, bool $delete) {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withMember($user, $role)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $project = Project::factory()->forWorkspace($workspace)->create();

    expect($user->can('view', $project))->toBe($view)
        ->and($user->can('update', $project))->toBe($update)
        ->and($user->can('delete', $project))->toBe($delete);
})->with([
    'admin' => [WorkspaceRole::Admin, true, true, true],
    'member' => [WorkspaceRole::Member, true, true, false],
    'viewer' => [WorkspaceRole::Viewer, true, false, false],
]);

test('non member cannot view', function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $project = Project::factory()->forWorkspace($workspace)->create();

    expect(User::factory()->create()->can('view', $project))->toBeFalse();
});
```

- [ ] **Step 2: Run to verify fail**

Run: `php artisan test tests/Feature/Project`
Expected: FAIL — `Project` not found.

- [ ] **Step 3: Migration**

```php
<?php

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Project\Enums\BusinessModel;
use App\Domain\Project\Enums\LegalEntityStatus;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Enums\Stage;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('phase', 16);
            $table->string('status', 16)->default('draft');
            $table->string('one_liner', 140)->nullable();
            $table->text('description_md')->nullable();
            $table->jsonb('description_doc')->nullable();
            $table->string('website_url')->nullable();
            $table->string('primary_domain')->nullable();
            $table->string('business_model', 16)->nullable();
            $table->string('industry')->nullable();
            $table->string('stage', 16)->nullable();
            $table->string('pricing_model')->nullable();
            $table->string('revenue_band')->nullable();
            $table->string('primary_market', 2)->nullable();
            $table->jsonb('target_markets')->nullable();
            $table->jsonb('languages')->nullable();
            $table->text('target_customer')->nullable();
            $table->text('problem_statement')->nullable();
            $table->text('solution_summary')->nullable();
            $table->string('legal_entity_status', 16)->nullable();
            $table->string('entity_type')->nullable();
            $table->string('jurisdiction')->nullable();
            $table->date('founded_on')->nullable();
            $table->unsignedInteger('team_size')->nullable();
            $table->string('timezone')->nullable();
            $table->string('currency', 3)->nullable();
            $table->jsonb('goals')->nullable();
            $table->jsonb('tech')->nullable();
            $table->text('context_snapshot_md')->nullable();
            $table->timestamp('context_built_at')->nullable();
            $table->jsonb('settings')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'status']);
        });
        EnumCheck::add('projects', 'phase', ProjectPhase::class);
        EnumCheck::add('projects', 'status', ProjectStatus::class);
        EnumCheck::add('projects', 'business_model', BusinessModel::class, nullable: true);
        EnumCheck::add('projects', 'stage', Stage::class, nullable: true);
        EnumCheck::add('projects', 'legal_entity_status', LegalEntityStatus::class, nullable: true);

        Schema::create('media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->nullableUlidMorphs('owner');
            $table->string('disk', 32);
            $table->string('path');
            $table->string('mime', 128);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('kind', 16);
            $table->string('checksum', 64);
            $table->timestamps();
        });
        EnumCheck::add('media', 'kind', MediaKind::class);

        Schema::create('project_brands', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('colors')->nullable();
            $table->jsonb('fonts')->nullable();
            $table->text('voice_md')->nullable();
            $table->jsonb('tone')->nullable();
            $table->foreignUlid('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->jsonb('logo_variants')->nullable();
            $table->jsonb('socials')->nullable();
            $table->timestamps();
        });

        Schema::create('project_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pack_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('pack_version');
            $table->foreignUlid('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at');
            $table->unique(['project_id', 'pack_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_packs');
        Schema::dropIfExists('project_brands');
        Schema::dropIfExists('media');
        Schema::dropIfExists('projects');
    }
};
```

- [ ] **Step 4: Models, factory, policy, Workspace relation**

`Project.php` essentials:
```php
#[Fillable([/* profile fields listed in Interfaces */])]
#[UseFactory(ProjectFactory::class)]
#[UsePolicy(ProjectPolicy::class)]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToWorkspace, HasFactory, HasUlids, SoftDeletes;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array { /* as in Interfaces */ }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isDraft(): bool
    {
        return $this->status === ProjectStatus::Draft;
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasOne<ProjectBrand, $this> */
    public function brand(): HasOne
    {
        return $this->hasOne(ProjectBrand::class);
    }

    /** @return Attribute<string|null, never> */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->brand?->logo?->url());
    }
}
```
`Task` model arrives in Task 7; reference it by `use App\Domain\Task\Models\Task;` now (only resolved when called).

`Media.php`: ULID, `BelongsToWorkspace`, casts `kind` → `MediaKind`; `url(): string` returns `Storage::disk($this->disk)->url($this->path)`.

`ProjectBrand.php`: ULID, fillable `colors, fonts, voice_md, tone, logo_media_id, logo_variants, socials`, `logo(): BelongsTo<Media>` via `logo_media_id`.

`ProjectPolicy.php`:
```php
<?php

namespace App\Domain\Project\Policies;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->roleIn($user) !== null;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->workspaceRole($project->workspace) !== null;
    }

    public function create(User $user): bool
    {
        return $this->roleIn($user)?->isAtLeast(WorkspaceRole::Member) ?? false;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->workspaceRole($project->workspace)?->isAtLeast(WorkspaceRole::Member) ?? false;
    }

    public function delete(User $user, Project $project): bool
    {
        $role = $user->workspaceRole($project->workspace);

        return $role !== null && ($role->isAtLeast(WorkspaceRole::Admin) || $project->owner_id === $user->id);
    }

    private function roleIn(User $user): ?WorkspaceRole
    {
        $workspace = currentWorkspace();

        return $workspace ? $user->workspaceRole($workspace) : null;
    }
}
```

`Workspace.php` add:
```php
/**
 * @return HasMany<Project, $this>
 */
public function projects(): HasMany
{
    return $this->hasMany(Project::class);
}
```

`ProjectFactory`:
```php
public function definition(): array
{
    $name = fake()->unique()->company();

    return [
        'workspace_id' => Workspace::factory(),
        'owner_id' => fn (array $attributes) => Workspace::find($attributes['workspace_id'])->owner_id,
        'name' => $name,
        'slug' => Str::slug($name),
        'phase' => ProjectPhase::Planning,
        'status' => ProjectStatus::Active,
        'one_liner' => fake()->sentence(8),
        'business_model' => BusinessModel::B2bSaas,
        'stage' => Stage::Idea,
        'primary_market' => 'US',
        'activated_at' => now(),
    ];
}

public function draft(): static
{
    return $this->state(['status' => ProjectStatus::Draft, 'activated_at' => null, 'one_liner' => null, 'stage' => null, 'business_model' => null, 'primary_market' => null]);
}

public function forWorkspace(Workspace $workspace): static
{
    return $this->state(['workspace_id' => $workspace->id, 'owner_id' => $workspace->owner_id]);
}
```
Factories bypass fillable (Eloquent factories use `forceFill`-like `newModel($attributes)` + unguarded) — fine.

- [ ] **Step 5: Run tests**

Run: `php artisan test tests/Feature/Project`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/*projects* app/Domain/Project app/Domain/Media app/Domain/Workspace/Models/Workspace.php database/factories/ProjectFactory.php tests/Feature/Project
git commit -m "feat: add projects, brands, media and project policy"
```

---

### Task 7: Tasks schema, models, factories

**Files:**
- Create: `database/migrations/2026_10_08_100300_create_tasks_tables.php`
- Create: `app/Domain/Task/Models/{Task,TaskAction}.php`, `app/Domain/Task/Policies/TaskPolicy.php`
- Create: `database/factories/{Task,TaskAction}Factory.php`
- Test: `tests/Feature/Task/TaskModelTest.php`

**Interfaces:**
- Produces:
  - `Task` (ULID, `BelongsToWorkspace`, `SoftDeletes`): relations `project()`, `parent()`, `children(): HasMany` (ordered `sort_order`), `actions(): HasMany<TaskAction>` (ordered), `dependencies(): BelongsToMany<Task>` (pivot `kind`, table `task_dependencies`, keys `task_id`/`depends_on_id`), `dependents(): BelongsToMany<Task>` (reverse); casts `status` → `TaskStatus`, `priority` → `TaskPriority`, `verification` → `Verification`, `completion_criteria|expected_outputs|body_doc` → `array`, dates → `immutable_datetime`; `isLeaf(): bool` (uses `children_count` when loaded, else `children()->exists()`).
  - Fillable on `Task`: `title, summary, body_md, body_doc, priority, due_at` only.
  - `TaskAction` (ULID): `task()`, `promptTemplate()`; casts `type` → `ActionType`, `executor` → `Executor`, `status` → `ActionStatus`, `config` → `array`. Fillable: none user-editable in P0 (`#[Fillable([])]`; Actions use `forceFill`).
  - `TaskFactory`: `forProject(Project $p)` (sets `project_id`, `workspace_id`), `childOf(Task $parent)` (depth + 1, same project), states `done()`, `locked()`.
  - `TaskActionFactory`: `forTask(Task $t)`.
  - `TaskPolicy`: `view(User, Task)` → `can('view', $task->project)`, `update(User, Task)` → `can('update', $task->project)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create();
});

test('task tree with children in order', function () {
    $root = Task::factory()->forProject($this->project)->create();
    $b = Task::factory()->childOf($root)->create(['sort_order' => 2]);
    $a = Task::factory()->childOf($root)->create(['sort_order' => 1]);

    expect($root->children->pluck('id')->all())->toBe([$a->id, $b->id])
        ->and($a->depth)->toBe(1)
        ->and($root->isLeaf())->toBeFalse()
        ->and($a->isLeaf())->toBeTrue()
        ->and($a->status)->toBe(TaskStatus::Todo);
});

test('a catalog task is copied into a project once', function () {
    $catalogTaskId = \App\Domain\Catalog\Models\CatalogTask::factory()->create()->id;
    Task::factory()->forProject($this->project)->create(['catalog_task_id' => $catalogTaskId]);

    Task::factory()->forProject($this->project)->create(['catalog_task_id' => $catalogTaskId]);
})->throws(QueryException::class);

test('depth is limited to 0..2', function () {
    Task::factory()->forProject($this->project)->create(['depth' => 3]);
})->throws(QueryException::class);
```

- [ ] **Step 2: Run to verify fail**

Run: `php artisan test --filter=TaskModelTest`
Expected: FAIL — `Task` not found.

- [ ] **Step 3: Migration**

```php
<?php

use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\DependencyKind;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_task_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('catalog_version')->nullable();
            $table->foreignUlid('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('category_key')->nullable();
            $table->string('title');
            $table->string('summary', 280)->nullable();
            $table->text('body_md')->nullable();
            $table->jsonb('body_doc')->nullable();
            $table->string('status', 24)->default('todo');
            $table->string('priority', 2)->default('p2');
            $table->timestamp('due_at')->nullable();
            $table->foreignUlid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('completion_criteria')->nullable();
            $table->jsonb('expected_outputs')->nullable();
            $table->string('verification', 24)->default('none');
            $table->unsignedSmallInteger('progress_pct')->default(0);
            $table->string('blocked_reason')->nullable();
            $table->string('skipped_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('completed_by_type', 16)->nullable();
            $table->ulid('completed_by_id')->nullable();
            $table->boolean('has_catalog_update')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'parent_id', 'sort_order']);
            $table->index(['project_id', 'status']);
        });
        EnumCheck::add('tasks', 'status', TaskStatus::class);
        EnumCheck::add('tasks', 'priority', TaskPriority::class);
        EnumCheck::add('tasks', 'verification', Verification::class);
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_depth_check CHECK (depth BETWEEN 0 AND 2)');
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_progress_check CHECK (progress_pct BETWEEN 0 AND 100)');
        DB::statement('CREATE UNIQUE INDEX tasks_project_catalog_unique ON tasks (project_id, catalog_task_id) WHERE catalog_task_id IS NOT NULL AND deleted_at IS NULL');
        DB::statement("CREATE INDEX tasks_open_idx ON tasks (project_id) WHERE status NOT IN ('done', 'skipped')");

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->foreignUlid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignUlid('depends_on_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('kind', 8)->default('hard');
            $table->primary(['task_id', 'depends_on_id']);
        });
        EnumCheck::add('task_dependencies', 'kind', DependencyKind::class);

        Schema::create('task_actions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_action_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16);
            $table->string('executor', 16);
            $table->string('title');
            $table->text('instructions_md')->nullable();
            $table->foreignId('prompt_template_id')->nullable()->constrained()->nullOnDelete();
            $table->text('prompt_override_md')->nullable();
            $table->jsonb('config')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('requires_approval')->default(false);
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('sort_order')->default(0);
            $table->ulid('last_run_id')->nullable();          // FK added in P1 with action_runs
            $table->timestamp('completed_at')->nullable();
            $table->string('completed_by_type', 16)->nullable();
            $table->ulid('completed_by_id')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'sort_order']);
        });
        EnumCheck::add('task_actions', 'type', ActionType::class);
        EnumCheck::add('task_actions', 'executor', Executor::class);
        EnumCheck::add('task_actions', 'status', ActionStatus::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('task_actions');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('tasks');
    }
};
```

- [ ] **Step 4: Models, factories, policy** — as in Interfaces. `Task::isLeaf()`:

```php
public function isLeaf(): bool
{
    return array_key_exists('children_count', $this->attributes)
        ? (int) $this->attributes['children_count'] === 0
        : ! $this->children()->exists();
}
```

`TaskFactory::childOf()`:
```php
public function childOf(Task $parent): static
{
    return $this->state([
        'project_id' => $parent->project_id,
        'workspace_id' => $parent->workspace_id,
        'parent_id' => $parent->id,
        'depth' => $parent->depth + 1,
        'category_key' => $parent->category_key,
    ]);
}
```
Default definition: `title`, `status` = `TaskStatus::Todo`, `priority` = `TaskPriority::P2`, `verification` = `Verification::None`, `depth` 0, `category_key` = `'general'`. `done()` sets `status=done`, `progress_pct=100`, `completed_at=now()`, `verification=self_reported`.

Register `TaskPolicy` with `#[UsePolicy(TaskPolicy::class)]` on `Task`.

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=TaskModelTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/*tasks* app/Domain/Task database/factories/Task*Factory.php tests/Feature/Task
git commit -m "feat: add tasks, task actions and dependencies"
```

---

### Task 8: ApplyPack (catalog snapshot into a project)

**Files:**
- Create: `app/Domain/Project/Actions/ApplyPack.php`, `app/Domain/Task/Actions/RefreshTaskLocks.php`
- Test: `tests/Feature/Project/ApplyPackTest.php`, `tests/Feature/Task/RefreshTaskLocksTest.php`

**Interfaces:**
- Consumes: `Pack`, `PackItem`, `CatalogTask`, `Project`, `Task`, `ActivityRecorder`, `Actor`.
- Produces:
  - `ApplyPack::handle(Project $project, Pack $pack, ?Actor $actor = null): int` (number of tasks created). Copies each pack item's catalog task (+ subtree when `include_subtree`), its actions, and dependencies between copied tasks. Skips catalog tasks the project already has. Records `project_packs` (upsert) and activity `project.pack_applied` `{pack, version, tasks_created}`. Calls `RefreshTaskLocks`.
  - `RefreshTaskLocks::handle(Project $project): void` — every non-closed task with a hard dependency whose status is not closed → `locked`; every `locked` task whose hard deps are all closed → `todo`. Leaves `in_progress/blocked/awaiting_approval/done/skipped` alone except: `todo|locked` only.

- [ ] **Step 1: Write failing tests**

`ApplyPackTest.php`:
```php
<?php

use App\Domain\Catalog\Models\CatalogAction;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Actions\ApplyPack;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create();

    $this->root = CatalogTask::factory()->create(['title' => 'Validate', 'version' => 3]);
    $this->recruit = CatalogTask::factory()->childOf($this->root)->create(['title' => 'Recruit', 'sort_order' => 1]);
    $this->call = CatalogTask::factory()->childOf($this->root)->create(['title' => 'Call', 'sort_order' => 2]);
    DB::table('catalog_task_dependencies')->insert(['task_id' => $this->call->id, 'depends_on_id' => $this->recruit->id, 'kind' => 'hard']);
    CatalogAction::factory()->create(['catalog_task_id' => $this->recruit->id, 'title' => 'Book calls']);

    $this->pack = Pack::factory()->withTasks($this->root)->create(['version' => 2]);
});

test('copies the subtree, actions and dependencies', function () {
    $created = app(ApplyPack::class)->handle($this->project, $this->pack);

    $root = Task::where('catalog_task_id', $this->root->id)->firstOrFail();
    $recruit = Task::where('catalog_task_id', $this->recruit->id)->firstOrFail();
    $call = Task::where('catalog_task_id', $this->call->id)->firstOrFail();

    expect($created)->toBe(3)
        ->and($root->depth)->toBe(0)
        ->and($root->catalog_version)->toBe(3)
        ->and($root->category_key)->toBe($this->root->category->key)
        ->and($recruit->parent_id)->toBe($root->id)
        ->and($recruit->depth)->toBe(1)
        ->and($recruit->actions->first()->title)->toBe('Book calls')
        ->and($recruit->actions->first()->status)->toBe(ActionStatus::Pending)
        ->and($call->dependencies->first()->id)->toBe($recruit->id)
        ->and($call->status)->toBe(TaskStatus::Locked)
        ->and($recruit->status)->toBe(TaskStatus::Todo)
        ->and($this->project->packs()->first()->pack_version)->toBe(2);
});

test('applying twice is idempotent', function () {
    app(ApplyPack::class)->handle($this->project, $this->pack);
    $created = app(ApplyPack::class)->handle($this->project, $this->pack);

    expect($created)->toBe(0)
        ->and(Task::count())->toBe(3)
        ->and($this->project->packs()->count())->toBe(1);
});

test('records activity', function () {
    app(ApplyPack::class)->handle($this->project, $this->pack);

    $this->assertDatabaseHas('activity_log', [
        'project_id' => $this->project->id,
        'event' => 'project.pack_applied',
    ]);
});

test('skips unpublished catalog tasks', function () {
    $this->call->forceFill(['status' => 'archived'])->save();

    expect(app(ApplyPack::class)->handle($this->project, $this->pack))->toBe(2);
});
```

`RefreshTaskLocksTest.php`:
```php
<?php

use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\RefreshTaskLocks;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;

beforeEach(function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $this->project = Project::factory()->forWorkspace($workspace)->create();
    $this->dep = Task::factory()->forProject($this->project)->create();
    $this->task = Task::factory()->forProject($this->project)->create();
    $this->task->dependencies()->attach($this->dep->id, ['kind' => 'hard']);
});

test('locks while a hard dependency is open and unlocks when closed', function () {
    app(RefreshTaskLocks::class)->handle($this->project);
    expect($this->task->fresh()->status)->toBe(TaskStatus::Locked);

    $this->dep->forceFill(['status' => TaskStatus::Done])->save();
    app(RefreshTaskLocks::class)->handle($this->project);
    expect($this->task->fresh()->status)->toBe(TaskStatus::Todo);
});

test('soft dependencies never lock', function () {
    $this->task->dependencies()->updateExistingPivot($this->dep->id, ['kind' => 'soft']);

    app(RefreshTaskLocks::class)->handle($this->project);

    expect($this->task->fresh()->status)->toBe(TaskStatus::Todo);
});

test('does not touch done tasks', function () {
    $this->task->forceFill(['status' => TaskStatus::Done])->save();

    app(RefreshTaskLocks::class)->handle($this->project);

    expect($this->task->fresh()->status)->toBe(TaskStatus::Done);
});
```

- [ ] **Step 2: Run to verify fail**

Run: `php artisan test --filter="ApplyPackTest|RefreshTaskLocksTest"`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement RefreshTaskLocks**

```php
<?php

namespace App\Domain\Task\Actions;

use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use Illuminate\Support\Facades\DB;

/**
 * DATA_MODEL §F.1: a task is locked while any hard dependency is not done/skipped.
 */
class RefreshTaskLocks
{
    public function handle(Project $project): void
    {
        $blocked = DB::table('task_dependencies as d')
            ->join('tasks as dep', 'dep.id', '=', 'd.depends_on_id')
            ->join('tasks as t', 't.id', '=', 'd.task_id')
            ->where('t.project_id', $project->id)
            ->where('d.kind', 'hard')
            ->whereNull('dep.deleted_at')
            ->whereNotIn('dep.status', [TaskStatus::Done->value, TaskStatus::Skipped->value])
            ->distinct()
            ->pluck('d.task_id');

        $project->tasks()
            ->where('status', TaskStatus::Todo)
            ->whereIn('id', $blocked)
            ->update(['status' => TaskStatus::Locked, 'updated_at' => now()]);

        $project->tasks()
            ->where('status', TaskStatus::Locked)
            ->whereNotIn('id', $blocked)
            ->update(['status' => TaskStatus::Todo, 'updated_at' => now()]);
    }
}
```

- [ ] **Step 4: Implement ApplyPack**

```php
<?php

namespace App\Domain\Project\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\RefreshTaskLocks;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Snapshots catalog rows into project tasks (CLAUDE.md rule 7). Idempotent.
 */
class ApplyPack
{
    public function __construct(
        protected ActivityRecorder $activity,
        protected RefreshTaskLocks $refreshLocks,
    ) {}

    public function handle(Project $project, Pack $pack, ?Actor $actor = null): int
    {
        $actor ??= Actor::current();

        return DB::transaction(function () use ($project, $pack, $actor): int {
            /** @var array<int, string> $map catalog_task_id => task id */
            $map = $project->tasks()->whereNotNull('catalog_task_id')->pluck('id', 'catalog_task_id')->all();
            $created = 0;

            foreach ($this->catalogTasksFor($pack) as [$catalogTask, $position]) {
                if (isset($map[$catalogTask->id])) {
                    continue;
                }

                $parentId = $map[$catalogTask->parent_id ?? 0] ?? null;
                $parentDepth = $parentId ? Task::query()->whereKey($parentId)->value('depth') : null;

                $task = new Task;
                $task->forceFill([
                    'project_id' => $project->id,
                    'workspace_id' => $project->workspace_id,
                    'catalog_task_id' => $catalogTask->id,
                    'catalog_version' => $catalogTask->version,
                    'parent_id' => $parentId,
                    'depth' => $parentDepth === null ? 0 : $parentDepth + 1,
                    'sort_order' => $position,
                    'category_key' => $catalogTask->category->key,
                    'title' => $catalogTask->title,
                    'summary' => $catalogTask->summary,
                    'body_md' => $catalogTask->body_md,
                    'body_doc' => $catalogTask->body_doc,
                    'status' => TaskStatus::Todo,
                    'priority' => $catalogTask->priority_default,
                    'completion_criteria' => $catalogTask->completion_criteria,
                    'expected_outputs' => $catalogTask->expected_outputs,
                    'verification' => Verification::None,
                ])->save();

                foreach ($catalogTask->actions as $catalogAction) {
                    (new TaskAction)->forceFill([
                        'task_id' => $task->id,
                        'project_id' => $project->id,
                        'catalog_action_id' => $catalogAction->id,
                        'type' => $catalogAction->type,
                        'executor' => $catalogAction->executor,
                        'title' => $catalogAction->title,
                        'instructions_md' => $catalogAction->instructions_md,
                        'prompt_template_id' => $catalogAction->prompt_template_id,
                        'config' => $catalogAction->config,
                        'is_required' => $catalogAction->is_required,
                        'requires_approval' => $catalogAction->requires_approval,
                        'status' => ActionStatus::Pending,
                        'sort_order' => $catalogAction->sort_order,
                    ])->save();
                }

                $map[$catalogTask->id] = $task->id;
                $created++;
            }

            $this->copyDependencies($map);

            $project->packs()->updateOrCreate(['pack_id' => $pack->id], [
                'pack_version' => $pack->version,
                'applied_by' => $actor->type->value === 'user' ? $actor->id : null,
                'applied_at' => now(),
            ]);

            $this->refreshLocks->handle($project);

            $this->activity->record('project.pack_applied', $project, [
                'pack' => $pack->key,
                'version' => $pack->version,
                'tasks_created' => $created,
            ], $actor);

            return $created;
        });
    }

    /**
     * Pack items + subtrees, parents before children, with sibling position.
     *
     * @return Collection<int, array{0: CatalogTask, 1: int}>
     */
    private function catalogTasksFor(Pack $pack): Collection
    {
        $all = CatalogTask::query()
            ->where('status', CatalogStatus::Published)
            ->with(['category', 'actions'])
            ->orderBy('sort_order')
            ->get();
        $byParent = $all->groupBy(fn (CatalogTask $t) => $t->parent_id ?? 0);
        $result = collect();

        $walk = function (CatalogTask $task, int $position, bool $subtree) use (&$walk, $byParent, $result): void {
            $result->push([$task, $position]);

            if ($subtree) {
                foreach ($byParent->get($task->id, collect())->values() as $i => $child) {
                    $walk($child, $i, true);
                }
            }
        };

        foreach ($pack->items()->get() as $i => $item) {
            $task = $all->firstWhere('id', $item->catalog_task_id);

            if ($task !== null) {
                $walk($task, $i, $item->include_subtree);
            }
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $map
     */
    private function copyDependencies(array $map): void
    {
        $rows = DB::table('catalog_task_dependencies')
            ->whereIn('task_id', array_keys($map))
            ->whereIn('depends_on_id', array_keys($map))
            ->get()
            ->map(fn (object $row): array => [
                'task_id' => $map[$row->task_id],
                'depends_on_id' => $map[$row->depends_on_id],
                'kind' => $row->kind,
            ])
            ->all();

        DB::table('task_dependencies')->insertOrIgnore($rows);
    }
}
```

`Project::packs()` returns `HasMany<ProjectPack>`; `ProjectPack` has `public $timestamps = false;` and fillable `pack_id, pack_version, applied_by, applied_at`.

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="ApplyPackTest|RefreshTaskLocksTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Project/Actions/ApplyPack.php app/Domain/Task/Actions/RefreshTaskLocks.php app/Domain/Project/Models tests/Feature/Project/ApplyPackTest.php tests/Feature/Task/RefreshTaskLocksTest.php
git commit -m "feat: apply catalog packs to projects with dependency locks"
```

---

### Task 9: CreateProject, UpdateProjectSetup, ActivateProject, UpdateProjectLogo

**Files:**
- Create: `app/Domain/Project/Enums/ProjectSetupStep.php`
- Create: `app/Domain/Project/Actions/{CreateProject,UpdateProjectSetup,ActivateProject,UpdateProjectLogo}.php`
- Test: `tests/Feature/Project/CreateProjectTest.php`, `tests/Feature/Project/ProjectSetupActionsTest.php`

**Interfaces:**
- Produces:
  - `ProjectSetupStep`: `identity, business, market, goals`; `next(): ?self`; `fields(): list<string>` (identity: `name, one_liner, description_md, website_url`; business: `business_model, stage, industry, pricing_model`; market: `primary_market, target_customer, problem_statement, solution_summary`; goals: `goals`); `rules(): array` (Laravel rules, listed in Step 3); `label(): string`; `static firstIncomplete(Project $p): ?self` (first step whose required fields are blank: identity→one_liner, business→business_model|stage, market→primary_market; goals is never "incomplete").
  - `CreateProject::handle(User $owner, ProjectPhase $phase, string $name): Project` — draft, unique slug per workspace, applies `Pack::defaultForPhase($phase)` when one exists, activity `project.created`.
  - `UpdateProjectSetup::handle(Project $project, ProjectSetupStep $step, array $data): Project` — fills only `$step->fields()`, activity `project.updated` `{step, changed: [keys]}`.
  - `ActivateProject::handle(Project $project): Project` — throws `ValidationException` (key = missing field) when any of `name, one_liner, stage, business_model, primary_market` is blank; sets `status=active`, `activated_at`; activity `project.activated`. No-op if already active.
  - `UpdateProjectLogo::handle(Project $project, UploadedFile $logo): Media` — stores on `public` disk under `project-logos/{project_id}`, creates `Media(kind=image)`, sets `brand.logo_media_id` (creates brand row if missing), deletes previous logo media + file; activity `project.logo_updated`.

- [ ] **Step 1: Write failing tests**

`CreateProjectTest.php`:
```php
<?php

use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Actions\CreateProject;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
});

test('creates a draft project with the phase default pack applied', function () {
    $task = CatalogTask::factory()->create();
    Pack::factory()->defaultFor(ProjectPhase::Selling)->withTasks($task)->create();

    $project = app(CreateProject::class)->handle($this->user, ProjectPhase::Selling, 'Acme Rockets');

    expect($project->status)->toBe(ProjectStatus::Draft)
        ->and($project->phase)->toBe(ProjectPhase::Selling)
        ->and($project->slug)->toBe('acme-rockets')
        ->and($project->owner_id)->toBe($this->user->id)
        ->and($project->workspace_id)->toBe($this->workspace->id)
        ->and($project->tasks()->count())->toBe(1);
});

test('creates an empty draft when the phase has no default pack', function () {
    $project = app(CreateProject::class)->handle($this->user, ProjectPhase::Developing, 'Solo');

    expect($project->tasks()->count())->toBe(0);
});

test('slug gets a suffix when taken in the workspace', function () {
    app(CreateProject::class)->handle($this->user, ProjectPhase::Planning, 'Acme');
    $second = app(CreateProject::class)->handle($this->user, ProjectPhase::Planning, 'Acme');

    expect($second->slug)->toBe('acme-2');
});

test('name without slug characters still gets a slug', function () {
    expect(app(CreateProject::class)->handle($this->user, ProjectPhase::Planning, '🚀🚀')->slug)->toBe('project');
});
```

`ProjectSetupActionsTest.php`:
```php
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
```

- [ ] **Step 2: Run to verify fail**

Run: `php artisan test --filter="CreateProjectTest|ProjectSetupActionsTest"`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement ProjectSetupStep**

```php
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
        $index = array_search($this, $cases, true);

        return $cases[$index + 1] ?? null;
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return array_keys($this->rules());
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
}
```

`fields()` returns `goals.*.title` etc. for Goals; `UpdateProjectSetup` must only use top-level keys: `array_values(array_unique(array_map(fn ($k) => strtok($k, '.'), $step->fields())))`.

- [ ] **Step 4: Implement the Actions**

`CreateProject.php`:
```php
<?php

namespace App\Domain\Project\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProject
{
    public function __construct(
        protected ApplyPack $applyPack,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(User $owner, ProjectPhase $phase, string $name): Project
    {
        $actor = Actor::user($owner);

        return DB::transaction(function () use ($owner, $phase, $name, $actor): Project {
            $project = new Project(['name' => $name]);
            $project->forceFill([
                'owner_id' => $owner->id,
                'slug' => $this->uniqueSlug($name),
                'phase' => $phase,
                'status' => ProjectStatus::Draft,
            ])->save();

            $this->activity->record('project.created', $project, ['phase' => $phase->value], $actor);

            if ($pack = Pack::defaultForPhase($phase)) {
                $this->applyPack->handle($project, $pack, $actor);
            }

            return $project;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;

        // Includes trashed: their slug stays reserved, like workspaces.
        for ($i = 2; Project::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
```

`UpdateProjectSetup.php`:
```php
public function handle(Project $project, ProjectSetupStep $step, array $data): Project
{
    $keys = array_values(array_unique(array_map(fn (string $k): string => strtok($k, '.'), $step->fields())));

    $project->fill(Arr::only($data, $keys));
    $changed = array_keys($project->getDirty());
    $project->save();

    if ($changed !== []) {
        $this->activity->record('project.updated', $project, ['step' => $step->value, 'changed' => $changed]);
    }

    return $project;
}
```

`ActivateProject.php`:
```php
public const array REQUIRED = ['name', 'one_liner', 'business_model', 'stage', 'primary_market'];

public function handle(Project $project): Project
{
    if (! $project->isDraft()) {
        return $project;
    }

    $missing = array_values(array_filter(self::REQUIRED, fn (string $field): bool => blank($project->getAttribute($field))));

    if ($missing !== []) {
        throw ValidationException::withMessages(
            array_fill_keys($missing, __('This field is required to finish setup.')),
        );
    }

    $project->forceFill(['status' => ProjectStatus::Active, 'activated_at' => now()])->save();
    $this->activity->record('project.activated', $project);

    return $project;
}
```
Note: the activation test expects keys in order `one_liner, business_model, stage, primary_market` — `REQUIRED` order above produces that (name is filled).

`UpdateProjectLogo.php`:
```php
public const string DISK = 'public';

public function handle(Project $project, UploadedFile $logo): Media
{
    return DB::transaction(function () use ($project, $logo): Media {
        $brand = $project->brand()->firstOrCreate();
        $previous = $brand->logo;
        [$width, $height] = getimagesize($logo->getRealPath()) ?: [null, null];

        $media = new Media;
        $media->forceFill([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'owner_type' => $brand->getMorphClass(),
            'owner_id' => $brand->id,
            'disk' => self::DISK,
            'path' => $logo->store("project-logos/{$project->id}", self::DISK),
            'mime' => $logo->getMimeType(),
            'size' => $logo->getSize(),
            'width' => $width,
            'height' => $height,
            'kind' => MediaKind::Image,
            'checksum' => hash_file('sha256', $logo->getRealPath()),
        ])->save();

        $brand->forceFill(['logo_media_id' => $media->id])->save();

        if ($previous !== null) {
            Storage::disk($previous->disk)->delete($previous->path);
            $previous->delete();
        }

        $this->activity->record('project.logo_updated', $project);

        return $media;
    });
}
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="CreateProjectTest|ProjectSetupActionsTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Project tests/Feature/Project
git commit -m "feat: create draft projects, setup steps, activation and logo"
```

---

### Task 10: Task status Actions — MarkTaskDone, ReopenTask, RollupTaskStatus

**Files:**
- Create: `app/Domain/Task/Exceptions/InvalidTaskTransition.php`
- Create: `app/Domain/Task/Actions/{MarkTaskDone,ReopenTask,RollupTaskStatus}.php`
- Test: `tests/Feature/Task/TaskCompletionActionsTest.php`

**Interfaces:**
- Consumes: `RefreshTaskLocks`, `ActivityRecorder`, `Actor`.
- Produces:
  - `InvalidTaskTransition extends \DomainException` with named constructors `notLeaf(Task)`, `locked(Task)`.
  - `MarkTaskDone::handle(Task $task, Actor $actor): Task` — leaf only; refuses `locked`; no-op when closed. Closes open actions (`done`, `completed_at`, `completed_by_*`), sets task `done`, `progress_pct=100`, `verification=self_reported`, `started_at ??= now`, `completed_*`. Then rollup + locks. Activity `task.completed`.
  - `ReopenTask::handle(Task $task, Actor $actor): Task` — leaf only; no-op unless closed. Actions completed by a user go back to `pending`; task → `todo`, `progress_pct=0`, `verification=none`, clears `completed_*`. Then rollup + locks. Activity `task.reopened`.
  - `RollupTaskStatus::handle(Task $changed, Actor $actor): void` — walks ancestors of `$changed`. For each: `progress_pct` = rounded mean of leaf `progress_pct` in its subtree; status = `done` if all children closed, else `in_progress` if any leaf has progress > 0 or status in (`in_progress`,`done`,`skipped`), else `todo` (keeps `locked` if it was locked). On status change: set/clear `completed_at`, `verification` (`self_reported` when done), activity `task.status_changed` `{from, to}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\MarkTaskDone;
use App\Domain\Task\Actions\ReopenTask;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $workspace = Workspace::factory()->ownedBy($this->user)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $this->project = Project::factory()->forWorkspace($workspace)->create();
    $this->actor = Actor::user($this->user);

    $this->root = Task::factory()->forProject($this->project)->create();
    $this->a = Task::factory()->childOf($this->root)->create();
    $this->b = Task::factory()->childOf($this->root)->create();
    $this->b1 = Task::factory()->childOf($this->b)->create();
    $this->b2 = Task::factory()->childOf($this->b)->create();
    $this->action = TaskAction::factory()->forTask($this->a)->create();
});

test('completing a leaf closes its actions and rolls progress up', function () {
    app(MarkTaskDone::class)->handle($this->a, $this->actor);

    expect($this->a->fresh()->status)->toBe(TaskStatus::Done)
        ->and($this->a->fresh()->verification)->toBe(Verification::SelfReported)
        ->and($this->a->fresh()->completed_by_id)->toBe($this->user->id)
        ->and($this->action->fresh()->status)->toBe(ActionStatus::Done)
        ->and($this->root->fresh()->progress_pct)->toBe(33)   // leaves a, b1, b2 → 100/3
        ->and($this->root->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($this->b->fresh()->status)->toBe(TaskStatus::Todo);
});

test('parent becomes done when all leaves are done', function () {
    foreach ([$this->a, $this->b1, $this->b2] as $leaf) {
        app(MarkTaskDone::class)->handle($leaf, $this->actor);
    }

    expect($this->b->fresh()->status)->toBe(TaskStatus::Done)
        ->and($this->root->fresh()->status)->toBe(TaskStatus::Done)
        ->and($this->root->fresh()->progress_pct)->toBe(100);
});

test('reopening a leaf reopens done ancestors', function () {
    foreach ([$this->a, $this->b1, $this->b2] as $leaf) {
        app(MarkTaskDone::class)->handle($leaf, $this->actor);
    }

    app(ReopenTask::class)->handle($this->b1, $this->actor);

    expect($this->b1->fresh()->status)->toBe(TaskStatus::Todo)
        ->and($this->b->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($this->b->fresh()->completed_at)->toBeNull()
        ->and($this->root->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($this->root->fresh()->progress_pct)->toBe(67);
});

test('parent tasks cannot be completed directly', function () {
    app(MarkTaskDone::class)->handle($this->root, $this->actor);
})->throws(InvalidTaskTransition::class);

test('locked tasks cannot be completed', function () {
    $this->b2->dependencies()->attach($this->b1->id, ['kind' => 'hard']);
    app(\App\Domain\Task\Actions\RefreshTaskLocks::class)->handle($this->project);

    app(MarkTaskDone::class)->handle($this->b2->fresh(), $this->actor);
})->throws(InvalidTaskTransition::class);

test('completing a dependency unlocks dependents and reopening re-locks them', function () {
    $this->b2->dependencies()->attach($this->b1->id, ['kind' => 'hard']);
    app(\App\Domain\Task\Actions\RefreshTaskLocks::class)->handle($this->project);

    app(MarkTaskDone::class)->handle($this->b1, $this->actor);
    expect($this->b2->fresh()->status)->toBe(TaskStatus::Todo);

    app(ReopenTask::class)->handle($this->b1->fresh(), $this->actor);
    expect($this->b2->fresh()->status)->toBe(TaskStatus::Locked);
});

test('completing twice is a no-op with one activity row', function () {
    app(MarkTaskDone::class)->handle($this->a, $this->actor);
    app(MarkTaskDone::class)->handle($this->a->fresh(), $this->actor);

    expect(\App\Domain\Activity\Models\Activity::where('event', 'task.completed')->count())->toBe(1);
});
```

- [ ] **Step 2: Run to verify fail**

Run: `php artisan test --filter=TaskCompletionActionsTest`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement**

`InvalidTaskTransition.php`:
```php
<?php

namespace App\Domain\Task\Exceptions;

use App\Domain\Task\Models\Task;
use DomainException;

class InvalidTaskTransition extends DomainException
{
    public static function notLeaf(Task $task): self
    {
        return new self(__('Complete the subtasks of ":title" instead.', ['title' => $task->title]));
    }

    public static function locked(Task $task): self
    {
        return new self(__('":title" is locked until its dependencies are done.', ['title' => $task->title]));
    }
}
```

`MarkTaskDone.php`:
```php
<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Facades\DB;

class MarkTaskDone
{
    public function __construct(
        protected RollupTaskStatus $rollup,
        protected RefreshTaskLocks $refreshLocks,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(Task $task, Actor $actor): Task
    {
        if ($task->status->isClosed()) {
            return $task;
        }

        if (! $task->isLeaf()) {
            throw InvalidTaskTransition::notLeaf($task);
        }

        if ($task->status === TaskStatus::Locked) {
            throw InvalidTaskTransition::locked($task);
        }

        return DB::transaction(function () use ($task, $actor): Task {
            $completed = [
                'completed_at' => now(),
                'completed_by_type' => $actor->type->value,
                'completed_by_id' => $actor->id,
            ];

            $task->actions()
                ->whereNotIn('status', [ActionStatus::Done, ActionStatus::Skipped])
                ->update(['status' => ActionStatus::Done, ...$completed, 'updated_at' => now()]);

            $from = $task->status;
            $task->forceFill([
                'status' => TaskStatus::Done,
                'progress_pct' => 100,
                'verification' => Verification::SelfReported,
                'started_at' => $task->started_at ?? now(),
                ...$completed,
            ])->save();

            $this->activity->record('task.completed', $task, ['from' => $from->value], $actor);
            $this->rollup->handle($task, $actor);
            $this->refreshLocks->handle($task->project);

            return $task;
        });
    }
}
```

`ReopenTask.php` mirrors it: guard `! $task->status->isClosed()` → return; `! isLeaf()` → `notLeaf`; actions where `status = done` and `completed_by_type = 'user'` → `pending`, null `completed_*`; task → `todo`, `progress_pct=0`, `verification=none`, null `completed_*`; activity `task.reopened`; rollup; locks.

`RollupTaskStatus.php`:
```php
<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Collection;

/**
 * DATA_MODEL §F.3: parent progress and status follow their leaves.
 * Synchronous in P0 (trees are small); becomes a debounced job in P1.
 */
class RollupTaskStatus
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(Task $changed, Actor $actor): void
    {
        if ($changed->parent_id === null) {
            return;
        }

        /** @var Collection<string, Task> $tasks */
        $tasks = Task::query()
            ->where('project_id', $changed->project_id)
            ->get(['id', 'parent_id', 'status', 'progress_pct', 'title', 'project_id', 'workspace_id', 'completed_at', 'verification'])
            ->keyBy('id');
        $children = $tasks->groupBy('parent_id');

        for ($id = $changed->parent_id; $id !== null; $id = $tasks[$id]->parent_id) {
            $this->recalculate($tasks[$id], $children, $actor);
        }
    }

    /**
     * @param  Collection<string|int, Collection<int, Task>>  $children
     */
    private function recalculate(Task $task, Collection $children, Actor $actor): void
    {
        $leaves = $this->leaves($task, $children);
        $direct = $children->get($task->id, collect());
        $from = $task->status;

        $progress = (int) round($leaves->avg('progress_pct') ?? 0);
        $to = match (true) {
            $direct->every(fn (Task $c): bool => $c->status->isClosed()) => TaskStatus::Done,
            $leaves->contains(fn (Task $l): bool => $l->progress_pct > 0 || $l->status === TaskStatus::InProgress) => TaskStatus::InProgress,
            $from === TaskStatus::Locked => TaskStatus::Locked,
            default => TaskStatus::Todo,
        };

        $task->forceFill([
            'progress_pct' => $progress,
            'status' => $to,
            'completed_at' => $to === TaskStatus::Done ? ($task->completed_at ?? now()) : null,
            'verification' => $to === TaskStatus::Done ? Verification::SelfReported : Verification::None,
        ]);

        if ($task->isDirty()) {
            $task->save();
        }

        if ($from !== $to) {
            $this->activity->record('task.status_changed', $task, ['from' => $from->value, 'to' => $to->value], $actor);
        }
    }

    /**
     * @param  Collection<string|int, Collection<int, Task>>  $children
     * @return Collection<int, Task>
     */
    private function leaves(Task $task, Collection $children): Collection
    {
        $direct = $children->get($task->id);

        if ($direct === null) {
            return collect([$task]);
        }

        return $direct->flatMap(fn (Task $child): Collection => $this->leaves($child, $children))->values();
    }
}
```

The `$tasks` map holds the in-memory versions, so each ancestor sees the freshly saved values of the one below it.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=TaskCompletionActionsTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Task tests/Feature/Task/TaskCompletionActionsTest.php
git commit -m "feat: complete and reopen tasks with progress rollup"
```

---

### Task 11: RenderFullPrompt

**Files:**
- Create: `app/Domain/Prompt/Actions/RenderFullPrompt.php`
- Test: `tests/Feature/Prompt/RenderFullPromptTest.php`

**Interfaces:**
- Produces: `RenderFullPrompt::handle(TaskAction $action): string`. Template = `prompt_override_md` ?? `promptTemplate.full_md` ?? built-in default. Placeholders `{{ project.<field> }}`, `{{ task.<field> }}`, `{{ action.<field> }}` with fields: project `name, one_liner, description_md, website_url, business_model, stage, industry, primary_market, target_customer, problem_statement, solution_summary, phase`; task `title, summary, body_md`; action `title, instructions_md`. Unknown → `''`. Enums render `->value`. Appends `COMPLETION_PROTOCOL`. Collapses 3+ blank lines to 2. Caps at `MAX_CHARS = 14000` (ending with `\n…[truncated]`).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Project\Models\Project;
use App\Domain\Prompt\Actions\RenderFullPrompt;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;

beforeEach(function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $this->project = Project::factory()->forWorkspace($workspace)->create(['name' => 'Acme', 'one_liner' => 'Rockets for cats']);
    $this->task = Task::factory()->forProject($this->project)->create(['title' => 'Pricing']);
});

test('fills project, task and action placeholders and appends the protocol', function () {
    $template = PromptTemplate::factory()->create(['full_md' => 'Help {{ project.name }} ({{project.one_liner}}) with {{ task.title }}: {{ action.title }}. {{ project.unknown }}End']);
    $action = TaskAction::factory()->forTask($this->task)->create(['title' => 'Draft tiers', 'prompt_template_id' => $template->id]);

    $prompt = app(RenderFullPrompt::class)->handle($action);

    expect($prompt)->toStartWith('Help Acme (Rockets for cats) with Pricing: Draft tiers. End')
        ->and($prompt)->toContain(RenderFullPrompt::COMPLETION_PROTOCOL);
});

test('override wins over template; default used when neither exists', function () {
    $action = TaskAction::factory()->forTask($this->task)->create(['prompt_override_md' => 'Custom {{ task.title }}']);
    expect(app(RenderFullPrompt::class)->handle($action))->toStartWith('Custom Pricing');

    $plain = TaskAction::factory()->forTask($this->task)->create(['title' => 'Write it', 'instructions_md' => 'Do X']);
    expect(app(RenderFullPrompt::class)->handle($plain))->toContain('Pricing')->toContain('Do X')->toContain('Acme');
});

test('output is capped at 14000 characters', function () {
    $this->task->forceFill(['body_md' => str_repeat('a', 20000)])->save();
    $action = TaskAction::factory()->forTask($this->task)->create(['prompt_override_md' => '{{ task.body_md }}']);

    $prompt = app(RenderFullPrompt::class)->handle($action);

    expect(mb_strlen($prompt))->toBeLessThanOrEqual(RenderFullPrompt::MAX_CHARS)
        ->and($prompt)->toEndWith('…[truncated]');
});
```

- [ ] **Step 2: Run to verify fail**

Run: `php artisan test --filter=RenderFullPromptTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Domain\Prompt\Actions;

use App\Domain\Task\Models\TaskAction;
use BackedEnum;
use Illuminate\Support\Str;

/**
 * Self-contained "full prompt" (PROJECT_CONTEXT §8) for users without the connector.
 * P1 adds the launcher prompt + deep link next to it.
 */
class RenderFullPrompt
{
    public const int MAX_CHARS = 14000;

    public const string COMPLETION_PROTOCOL = <<<'MD'
        ---
        When you finish:
        1. Check the result against the task goal above.
        2. List the outputs you produced.
        3. List any follow-up tasks.
        4. Paste the result back into Founder OS and mark the step done.
        MD;

    private const string DEFAULT_TEMPLATE = <<<'MD'
        You are helping the founder of {{ project.name }} — {{ project.one_liner }}.
        Business model: {{ project.business_model }}. Stage: {{ project.stage }}. Market: {{ project.primary_market }}.

        ## Task: {{ task.title }}
        {{ task.body_md }}

        ## Step: {{ action.title }}
        {{ action.instructions_md }}
        MD;

    private const array FIELDS = [
        'project' => ['name', 'one_liner', 'description_md', 'website_url', 'business_model', 'stage', 'industry', 'primary_market', 'target_customer', 'problem_statement', 'solution_summary', 'phase'],
        'task' => ['title', 'summary', 'body_md'],
        'action' => ['title', 'instructions_md'],
    ];

    public function handle(TaskAction $action): string
    {
        $action->loadMissing(['promptTemplate', 'task.project']);

        $template = $action->prompt_override_md
            ?? $action->promptTemplate?->full_md
            ?? self::DEFAULT_TEMPLATE;

        $models = ['project' => $action->task->project, 'task' => $action->task, 'action' => $action];

        $body = preg_replace_callback('/\{\{\s*([a-z_]+)\.([a-z_]+)\s*\}\}/', function (array $m) use ($models): string {
            if (! in_array($m[2], self::FIELDS[$m[1]] ?? [], true)) {
                return '';
            }

            $value = $models[$m[1]]->getAttribute($m[2]);

            return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
        }, $template) ?? '';

        $prompt = preg_replace("/\n{3,}/", "\n\n", trim($body)."\n\n".self::COMPLETION_PROTOCOL) ?? '';

        return mb_strlen($prompt) > self::MAX_CHARS
            ? Str::substr($prompt, 0, self::MAX_CHARS - 13)."\n…[truncated]"
            : $prompt;
    }
}
```

The whitelist (`FIELDS`) keeps templates from reading arbitrary attributes (e.g. `owner_id`, `settings`). `…[truncated]` is 12 chars + newline = 13.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=RenderFullPromptTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Prompt tests/Feature/Prompt
git commit -m "feat: render full prompts for task actions"
```

---

### Task 12: Read models — ProjectTaskTree, TaskDetail, ProjectPageProps

**Files:**
- Create: `app/Domain/Task/Queries/{ProjectTaskTree,TaskDetail}.php`, `app/Domain/Project/Data/ProjectPageProps.php`
- Test: `tests/Feature/Task/ProjectTaskTreeTest.php`

**Interfaces:**
- Consumes: `Task`, `CatalogCategory`, `RenderFullPrompt::handle(TaskAction): string` (Task 11).
- Produces (array shapes match `resources/js/types/project.ts`):
  - `ProjectTaskTree::handle(Project $project): array{progressPct:int, groups: list<array{key:string, name:string, icon:?string, progressPct:int, tasks: list<TreeNode>}>}`; `TreeNode = array{id:string, title:string, status:string, progressPct:int, depth:int, children: list<TreeNode>}`. Groups ordered by catalog category `sort_order`, unknown keys last under name `Other`. Project progress = mean of all leaf `progress_pct`.
  - `TaskDetail::handle(Task $task): array{id, title, summary, bodyMd, status, statusLabel, progressPct, depth, isLeaf, priority, verification, completedAt, ancestors: list<array{id,title}>, parent: ?array{id,title,progressPct,status}, children: list<array{id,title,status,progressPct,isLeaf}>, actions: list<array{id,title,type,executor,status,instructionsMd,isRequired,prompt:string}>}`
  - `ProjectPageProps::for(Project $project, User $user): array{project: array{id,slug,name,oneLiner,phase,phaseLabel,status,logoUrl,setupStep:?string}, tree: <ProjectTaskTree>, can: array{update:bool}}`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Queries\ProjectTaskTree;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;

test('groups root tasks by category with nested children and progress', function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $project = Project::factory()->forWorkspace($workspace)->create();
    CatalogCategory::factory()->create(['key' => 'launch', 'name' => 'Launch', 'sort_order' => 2]);
    CatalogCategory::factory()->create(['key' => 'idea', 'name' => 'Idea', 'sort_order' => 1]);

    $launch = Task::factory()->forProject($project)->create(['category_key' => 'launch', 'title' => 'Launch']);
    $idea = Task::factory()->forProject($project)->create(['category_key' => 'idea', 'title' => 'Idea']);
    Task::factory()->childOf($idea)->done()->create(['title' => 'Interview']);
    Task::factory()->childOf($idea)->create(['title' => 'Survey']);

    $tree = app(ProjectTaskTree::class)->handle($project);

    expect(array_column($tree['groups'], 'key'))->toBe(['idea', 'launch'])
        ->and($tree['groups'][0]['tasks'][0]['children'])->toHaveCount(2)
        ->and($tree['groups'][0]['progressPct'])->toBe(50)
        ->and($tree['progressPct'])->toBe(33); // leaves: Interview 100, Survey 0, Launch 0
});
```

- [ ] **Step 2: Run to verify fail**

Run: `php artisan test --filter=ProjectTaskTreeTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement ProjectTaskTree**

```php
<?php

namespace App\Domain\Task\Queries;

use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Collection;

class ProjectTaskTree
{
    /**
     * @return array{progressPct: int, groups: list<array<string, mixed>>}
     */
    public function handle(Project $project): array
    {
        $tasks = $project->tasks()
            ->orderBy('depth')->orderBy('sort_order')
            ->get(['id', 'parent_id', 'title', 'status', 'progress_pct', 'depth', 'category_key']);
        $children = $tasks->groupBy(fn (Task $t) => $t->parent_id ?? 'root');
        $categories = CatalogCategory::query()
            ->whereIn('key', $tasks->pluck('category_key')->filter()->unique())
            ->get()->keyBy('key');

        $groups = $children->get('root', collect())
            ->groupBy(fn (Task $t) => $t->category_key ?? 'other')
            ->map(function (Collection $roots, string $key) use ($categories, $children): array {
                $category = $categories->get($key);

                return [
                    'key' => $key,
                    'name' => $category->name ?? __('Other'),
                    'icon' => $category?->icon,
                    'sortOrder' => $category->sort_order ?? PHP_INT_MAX,
                    'progressPct' => $this->meanLeafProgress($roots, $children),
                    'tasks' => $roots->map(fn (Task $t) => $this->node($t, $children))->values()->all(),
                ];
            })
            ->sortBy('sortOrder')
            ->map(fn (array $group) => collect($group)->except('sortOrder')->all())
            ->values()
            ->all();

        return [
            'progressPct' => $this->meanLeafProgress($children->get('root', collect()), $children),
            'groups' => $groups,
        ];
    }

    /**
     * @param  Collection<string, Collection<int, Task>>  $children
     * @return array<string, mixed>
     */
    private function node(Task $task, Collection $children): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status->value,
            'progressPct' => $task->progress_pct,
            'depth' => $task->depth,
            'children' => $children->get($task->id, collect())->map(fn (Task $c) => $this->node($c, $children))->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Task>  $roots
     * @param  Collection<string, Collection<int, Task>>  $children
     */
    private function meanLeafProgress(Collection $roots, Collection $children): int
    {
        $leaves = fn (Task $t): Collection => $children->has($t->id)
            ? $children->get($t->id)->flatMap(fn (Task $c) => $leaves($c))
            : collect([$t]);

        return (int) round($roots->flatMap($leaves)->avg('progress_pct') ?? 0);
    }
}
```

(The `$leaves` closure is recursive — declare it with `$leaves = null; $leaves = function (...) use (&$leaves, $children) {...}`.)

- [ ] **Step 4: Implement TaskDetail and ProjectPageProps**

`TaskDetail::handle()`:
```php
public function __construct(protected RenderFullPrompt $renderPrompt) {}

public function handle(Task $task): array
{
    $task->loadMissing(['actions.promptTemplate', 'children' => fn ($q) => $q->withCount('children'), 'parent', 'project']);
    $ancestors = [];

    for ($node = $task->parent; $node !== null; $node = $node->parent) {
        array_unshift($ancestors, ['id' => $node->id, 'title' => $node->title]);
    }

    return [
        'id' => $task->id,
        'title' => $task->title,
        'summary' => $task->summary,
        'bodyMd' => $task->body_md,
        'status' => $task->status->value,
        'statusLabel' => $task->status->label(),
        'progressPct' => $task->progress_pct,
        'depth' => $task->depth,
        'isLeaf' => $task->children->isEmpty(),
        'priority' => $task->priority->value,
        'verification' => $task->verification->value,
        'completedAt' => $task->completed_at?->toIso8601String(),
        'ancestors' => $ancestors,
        'parent' => $task->parent ? [
            'id' => $task->parent->id,
            'title' => $task->parent->title,
            'progressPct' => $task->parent->progress_pct,
            'status' => $task->parent->status->value,
        ] : null,
        'children' => $task->children->map(fn (Task $c): array => [
            'id' => $c->id,
            'title' => $c->title,
            'status' => $c->status->value,
            'progressPct' => $c->progress_pct,
            'isLeaf' => $c->children_count === 0,
        ])->all(),
        'actions' => $task->actions->map(fn (TaskAction $a): array => [
            'id' => $a->id,
            'title' => $a->title,
            'type' => $a->type->value,
            'executor' => $a->executor->value,
            'status' => $a->status->value,
            'instructionsMd' => $a->instructions_md,
            'isRequired' => $a->is_required,
            'prompt' => $this->renderPrompt->handle($a),
        ])->all(),
    ];
}
```

`ProjectPageProps::for()`:
```php
public function __construct(protected ProjectTaskTree $tree) {}

public function for(Project $project, User $user): array
{
    return [
        'project' => [
            'id' => $project->id,
            'slug' => $project->slug,
            'name' => $project->name,
            'oneLiner' => $project->one_liner,
            'phase' => $project->phase->value,
            'phaseLabel' => $project->phase->label(),
            'status' => $project->status->value,
            'logoUrl' => $project->logo_url,
            'setupStep' => $project->isDraft() ? (ProjectSetupStep::firstIncomplete($project) ?? ProjectSetupStep::Goals)->value : null,
        ],
        'tree' => $this->tree->handle($project),
        'can' => ['update' => $user->can('update', $project)],
    ];
}
```
`ProjectPageProps` is a service (resolve from container), despite living in `Data/`.

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=ProjectTaskTreeTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Task/Queries app/Domain/Project/Data tests/Feature/Task/ProjectTaskTreeTest.php
git commit -m "feat: add project task tree and task detail read models"
```

---

### Task 13: HTTP layer — routes, controllers, requests

**Files:**
- Create: `routes/projects.php`; Modify: `routes/workspace.php` (inside the tenant group, after `Route::inertia('dashboard', …)`: `require __DIR__.'/projects.php';`)
- Create: `app/Domain/Project/Http/Controllers/{ProjectController,ProjectSetupController,ProjectLogoController}.php`
- Create: `app/Domain/Project/Http/Requests/{StoreProjectRequest,UpdateProjectSetupRequest,UpdateProjectLogoRequest}.php`
- Create: `app/Domain/Task/Http/Controllers/{TaskController,TaskCompletionController}.php`
- Test: `tests/Feature/Project/ProjectHttpTest.php`, `tests/Feature/Project/ProjectSetupHttpTest.php`, `tests/Feature/Task/TaskHttpTest.php`, `tests/Feature/Project/ProjectIsolationTest.php`

**Interfaces:**
- Produces routes (all under `/{workspace}`, names as listed — Wayfinder generates `@/routes/projects/...`):

| Method | URI | Name | Controller@method | Page / response |
| --- | --- | --- | --- | --- |
| GET | `projects` | `projects.index` | `ProjectController@index` | `projects/index` |
| GET | `projects/create` | `projects.create` | `ProjectController@create` | `projects/create` |
| POST | `projects` | `projects.store` | `ProjectController@store` | → `projects.setup.edit` step identity |
| GET | `projects/{project}` | `projects.show` | `ProjectController@show` | `projects/overview` |
| GET | `projects/{project}/setup/{step}` | `projects.setup.edit` | `ProjectSetupController@edit` | `projects/setup` |
| PATCH | `projects/{project}/setup/{step}` | `projects.setup.update` | `ProjectSetupController@update` | → next step, or activate → `projects.show` |
| POST | `projects/{project}/logo` | `projects.logo.store` | `ProjectLogoController@store` | back |
| GET | `projects/{project}/tasks/{task}` | `projects.tasks.show` | `TaskController@show` | `projects/tasks/show` |
| POST | `projects/{project}/tasks/{task}/completion` | `projects.tasks.completion.store` | `TaskCompletionController@store` | back |
| DELETE | `projects/{project}/tasks/{task}/completion` | `projects.tasks.completion.destroy` | `TaskCompletionController@destroy` | back |

- Page props:
  - `projects/index`: `projects: list<{id, slug, name, oneLiner, phase, phaseLabel, status, logoUrl, progressPct}>`, `can: {create: bool}`
  - `projects/create`: `phases: ProjectPhase::options()`
  - `projects/setup`: `project: {slug, name, status, ...all wizard fields}`, `step: string`, `steps: list<{value,label}>`, `options: {businessModels: list<{value,label}>, stages: list<{value,label}>}`, `logoUrl: ?string`
  - `projects/overview`: `ProjectPageProps` + `nextTask: ?{id, title}` (first open leaf in tree order that is not locked)
  - `projects/tasks/show`: `ProjectPageProps` + `task: TaskDetail`

- [ ] **Step 1: Write failing HTTP tests**

`ProjectHttpTest.php`:
```php
<?php

use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    $this->actingAs($this->user);
});

test('lists projects of the workspace', function () {
    app(WorkspaceDiscoveryService::class)->runAs($this->workspace, fn () => Project::factory()->forWorkspace($this->workspace)->create(['name' => 'Rocket']));

    $this->get('/acme/projects')
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/index')
            ->has('projects', 1)
            ->where('projects.0.name', 'Rocket'));
});

test('create page lists the three phases', function () {
    $this->get('/acme/projects/create')
        ->assertInertia(fn (Assert $page) => $page->component('projects/create')->has('phases', 3));
});

test('store creates a draft with tasks and goes to setup', function () {
    $task = CatalogTask::factory()->create();
    Pack::factory()->defaultFor(ProjectPhase::Developing)->withTasks($task)->create();

    $this->post('/acme/projects', ['phase' => 'developing', 'name' => 'Rocket'])
        ->assertRedirect('/acme/projects/rocket/setup/identity');

    $project = Project::forWorkspace($this->workspace)->firstOrFail();
    expect($project->isDraft())->toBeTrue()
        ->and($project->tasks()->count())->toBe(1);
});

test('store validates phase and name', function () {
    $this->post('/acme/projects', ['phase' => 'dreaming', 'name' => ''])
        ->assertSessionHasErrors(['phase', 'name']);
});

test('overview renders tree and next task', function () {
    $project = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket']);
        \App\Domain\Task\Models\Task::factory()->forProject($project)->create(['title' => 'First']);

        return $project;
    });

    $this->get('/acme/projects/rocket')
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/overview')
            ->where('project.slug', 'rocket')
            ->has('tree.groups', 1)
            ->where('nextTask.title', 'First'));
});
```

`ProjectSetupHttpTest.php`:
```php
<?php

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    $this->project = app(WorkspaceDiscoveryService::class)->runAs($this->workspace,
        fn () => Project::factory()->forWorkspace($this->workspace)->draft()->create(['slug' => 'rocket']));
    $this->actingAs($this->user);
});

test('walks the wizard and activates on the last step', function () {
    $this->get('/acme/projects/rocket/setup/identity')
        ->assertInertia(fn (Assert $page) => $page->component('projects/setup')->where('step', 'identity')->has('steps', 4));

    $this->patch('/acme/projects/rocket/setup/identity', ['name' => 'Rocket', 'one_liner' => 'Rockets for cats'])
        ->assertRedirect('/acme/projects/rocket/setup/business');
    $this->patch('/acme/projects/rocket/setup/business', ['business_model' => 'b2c_app', 'stage' => 'idea'])
        ->assertRedirect('/acme/projects/rocket/setup/market');
    $this->patch('/acme/projects/rocket/setup/market', ['primary_market' => 'DE'])
        ->assertRedirect('/acme/projects/rocket/setup/goals');
    $this->patch('/acme/projects/rocket/setup/goals', ['goals' => [['title' => '10 customers']]])
        ->assertRedirect('/acme/projects/rocket');

    expect($this->project->fresh()->isDraft())->toBeFalse()
        ->and($this->project->fresh()->goals)->toBe([['title' => '10 customers']]);
});

test('finishing with missing fields sends the user back to that step', function () {
    $this->patch('/acme/projects/rocket/setup/goals', ['goals' => []])
        ->assertRedirect('/acme/projects/rocket/setup/identity')
        ->assertSessionHasErrors('one_liner');
});

test('unknown step is 404', function () {
    $this->get('/acme/projects/rocket/setup/finance')->assertNotFound();
});

test('step validation errors', function () {
    $this->patch('/acme/projects/rocket/setup/market', ['primary_market' => 'germany'])
        ->assertSessionHasErrors('primary_market');
});

test('logo upload', function () {
    Storage::fake('public');

    $this->post('/acme/projects/rocket/logo', ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
        ->assertRedirect();

    expect($this->project->fresh()->brand->logo_media_id)->not->toBeNull();
});

test('logo rejects non images', function () {
    $this->post('/acme/projects/rocket/logo', ['logo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('logo');
});
```

`TaskHttpTest.php`:
```php
<?php

use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    [$this->project, $this->parent, $this->leaf] = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket']);
        $parent = Task::factory()->forProject($project)->create(['title' => 'Parent']);
        $leaf = Task::factory()->childOf($parent)->create(['title' => 'Leaf']);

        return [$project, $parent, $leaf];
    });
    $this->actingAs($this->user);
});

test('subtask page has breadcrumb parent and prompt per action', function () {
    app(WorkspaceDiscoveryService::class)->runAs($this->workspace,
        fn () => \App\Domain\Task\Models\TaskAction::factory()->forTask($this->leaf)->create());

    $this->get("/acme/projects/rocket/tasks/{$this->leaf->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/tasks/show')
            ->where('task.title', 'Leaf')
            ->where('task.parent.title', 'Parent')
            ->where('task.ancestors.0.title', 'Parent')
            ->has('task.actions.0.prompt')
            ->has('tree.groups'));
});

test('complete and reopen a leaf', function () {
    $this->post("/acme/projects/rocket/tasks/{$this->leaf->id}/completion")->assertRedirect();
    expect($this->leaf->fresh()->status)->toBe(TaskStatus::Done)
        ->and($this->parent->fresh()->status)->toBe(TaskStatus::Done);

    $this->delete("/acme/projects/rocket/tasks/{$this->leaf->id}/completion")->assertRedirect();
    expect($this->leaf->fresh()->status)->toBe(TaskStatus::Todo);
});

test('completing a parent flashes an error toast and changes nothing', function () {
    $this->post("/acme/projects/rocket/tasks/{$this->parent->id}/completion")
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'error');

    expect($this->parent->fresh()->status)->toBe(TaskStatus::Todo);
});

test('viewer can read but not complete', function () {
    $viewer = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $viewer->id, 'role' => WorkspaceRole::Viewer, 'joined_at' => now()]);

    $this->actingAs($viewer)->get("/acme/projects/rocket/tasks/{$this->leaf->id}")->assertOk();
    $this->actingAs($viewer)->post("/acme/projects/rocket/tasks/{$this->leaf->id}/completion")->assertForbidden();
});
```

(If `assertInertiaFlash` does not exist in the installed inertia-laravel, assert `session('inertia.flash_data')` per how `WorkspaceSettingsTest` checks toasts — open that test and copy its assertion.)

`ProjectIsolationTest.php`:
```php
<?php

use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->a = Workspace::factory()->ownedBy($this->alice)->create(['slug' => 'alpha']);
    $this->b = Workspace::factory()->create(['slug' => 'beta']);
    [$this->projectB, $this->taskB] = app(WorkspaceDiscoveryService::class)->runAs($this->b, function () {
        $project = Project::factory()->forWorkspace($this->b)->create(['slug' => 'secret']);

        return [$project, Task::factory()->forProject($project)->create()];
    });
    app(WorkspaceDiscoveryService::class)->runAs($this->a,
        fn () => Project::factory()->forWorkspace($this->a)->create(['slug' => 'mine']));
    $this->actingAs($this->alice);
});

test('cannot open a project of another workspace through own workspace url', function () {
    $this->get('/alpha/projects/secret')->assertNotFound();
});

test('cannot open a task of another project through own project url', function () {
    $this->get("/alpha/projects/mine/tasks/{$this->taskB->id}")->assertNotFound();
});

test('cannot complete a task of another workspace', function () {
    $this->post("/alpha/projects/mine/tasks/{$this->taskB->id}/completion")->assertNotFound();
    $this->post("/beta/projects/secret/tasks/{$this->taskB->id}/completion")->assertForbidden();
});
```

- [ ] **Step 2: Run to verify fail**

Run: `php artisan test tests/Feature/Project/ProjectHttpTest.php tests/Feature/Project/ProjectSetupHttpTest.php tests/Feature/Task/TaskHttpTest.php tests/Feature/Project/ProjectIsolationTest.php`
Expected: FAIL — 404 on all routes.

- [ ] **Step 3: Routes**

`routes/projects.php`:
```php
<?php

use App\Domain\Project\Enums\ProjectSetupStep;
use App\Domain\Project\Http\Controllers\ProjectController;
use App\Domain\Project\Http\Controllers\ProjectLogoController;
use App\Domain\Project\Http\Controllers\ProjectSetupController;
use App\Domain\Task\Http\Controllers\TaskCompletionController;
use App\Domain\Task\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /{workspace} tenant group (routes/workspace.php).
*/

Route::prefix('projects')->name('projects.')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('index');
    Route::get('create', [ProjectController::class, 'create'])->name('create');
    Route::post('/', [ProjectController::class, 'store'])->name('store');
    Route::get('{project}', [ProjectController::class, 'show'])->name('show');

    Route::get('{project}/setup/{step}', [ProjectSetupController::class, 'edit'])
        ->whereIn('step', array_column(ProjectSetupStep::cases(), 'value'))
        ->name('setup.edit');
    Route::patch('{project}/setup/{step}', [ProjectSetupController::class, 'update'])
        ->whereIn('step', array_column(ProjectSetupStep::cases(), 'value'))
        ->name('setup.update');
    Route::post('{project}/logo', [ProjectLogoController::class, 'store'])->name('logo.store');

    Route::get('{project}/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::post('{project}/tasks/{task}/completion', [TaskCompletionController::class, 'store'])->name('tasks.completion.store');
    Route::delete('{project}/tasks/{task}/completion', [TaskCompletionController::class, 'destroy'])->name('tasks.completion.destroy');
});
```

`{step}` binds to `ProjectSetupStep` by type-hinting the enum in the controller (implicit enum binding). `scopeBindings()` on the tenant group makes `{project}` resolve via `$workspace->projects()` and `{task}` via `$project->tasks()` → wrong-parent ids 404.

- [ ] **Step 4: Controllers**

`ProjectController.php`:
```php
<?php

namespace App\Domain\Project\Http\Controllers;

use App\Domain\Project\Actions\CreateProject;
use App\Domain\Project\Data\ProjectPageProps;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Enums\ProjectSetupStep;
use App\Domain\Project\Http\Requests\StoreProjectRequest;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request, Workspace $workspace, \App\Domain\Task\Queries\ProjectTaskTree $tree): Response
    {
        Gate::authorize('viewAny', Project::class);

        $projects = $workspace->projects()->with('brand.logo')->latest()->get();

        return Inertia::render('projects/index', [
            'projects' => $projects->map(fn (Project $p): array => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'oneLiner' => $p->one_liner,
                'phase' => $p->phase->value,
                'phaseLabel' => $p->phase->label(),
                'status' => $p->status->value,
                'logoUrl' => $p->logo_url,
                'progressPct' => $tree->handle($p)['progressPct'],
            ]),
            'can' => ['create' => $request->user()->can('create', Project::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('projects/create', ['phases' => ProjectPhase::options()]);
    }

    public function store(StoreProjectRequest $request, CreateProject $createProject): RedirectResponse
    {
        $project = $createProject->handle(
            $request->user(),
            $request->enum('phase', ProjectPhase::class),
            $request->string('name')->trim()->toString(),
        );

        return to_route('projects.setup.edit', ['project' => $project->slug, 'step' => ProjectSetupStep::Identity]);
    }

    public function show(Request $request, Workspace $workspace, Project $project, ProjectPageProps $props): Response
    {
        Gate::authorize('view', $project);

        $nextTask = $project->tasks()
            ->whereNotIn('status', [TaskStatus::Done, TaskStatus::Skipped, TaskStatus::Locked])
            ->whereDoesntHave('children')
            ->orderBy('depth')->orderBy('sort_order')
            ->first(['id', 'title']);

        return Inertia::render('projects/overview', [
            ...$props->for($project, $request->user()),
            'nextTask' => $nextTask?->only(['id', 'title']),
        ]);
    }
}
```

`index` computes the tree per project (N queries) — acceptable for P0 (few projects per workspace); P1 caches `progress_pct` on `projects`. Note this in a code comment.

`StoreProjectRequest`:
```php
public function authorize(): bool
{
    return $this->user()->can('create', Project::class);
}

public function rules(): array
{
    return [
        'phase' => ['required', Rule::enum(ProjectPhase::class)],
        'name' => ['required', 'string', 'max:120'],
    ];
}
```

`ProjectSetupController.php`:
```php
<?php

namespace App\Domain\Project\Http\Controllers;

use App\Domain\Project\Actions\ActivateProject;
use App\Domain\Project\Actions\UpdateProjectSetup;
use App\Domain\Project\Enums\BusinessModel;
use App\Domain\Project\Enums\ProjectSetupStep;
use App\Domain\Project\Enums\Stage;
use App\Domain\Project\Http\Requests\UpdateProjectSetupRequest;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectSetupController extends Controller
{
    public function edit(Workspace $workspace, Project $project, ProjectSetupStep $step): Response
    {
        Gate::authorize('update', $project);

        $options = fn (string $enum): array => array_map(
            fn ($case): array => ['value' => $case->value, 'label' => $case->label()],
            $enum::cases(),
        );

        return Inertia::render('projects/setup', [
            'project' => [
                'slug' => $project->slug,
                'status' => $project->status->value,
                ...$project->only(['name', 'one_liner', 'description_md', 'website_url', 'industry', 'pricing_model', 'primary_market', 'target_customer', 'problem_statement', 'solution_summary']),
                'business_model' => $project->business_model?->value,
                'stage' => $project->stage?->value,
                'goals' => $project->goals ?? [],
            ],
            'logoUrl' => $project->logo_url,
            'step' => $step->value,
            'steps' => array_map(fn (ProjectSetupStep $s): array => ['value' => $s->value, 'label' => $s->label()], ProjectSetupStep::cases()),
            'options' => ['businessModels' => $options(BusinessModel::class), 'stages' => $options(Stage::class)],
        ]);
    }

    public function update(
        UpdateProjectSetupRequest $request,
        Workspace $workspace,
        Project $project,
        ProjectSetupStep $step,
        UpdateProjectSetup $updateSetup,
        ActivateProject $activate,
    ): RedirectResponse {
        $updateSetup->handle($project, $step, $request->validated());

        if ($next = $step->next()) {
            return to_route('projects.setup.edit', ['project' => $project->slug, 'step' => $next]);
        }

        try {
            $activate->handle($project);
        } catch (ValidationException $e) {
            return to_route('projects.setup.edit', [
                'project' => $project->slug,
                'step' => ProjectSetupStep::firstIncomplete($project) ?? ProjectSetupStep::Identity,
            ])->withErrors($e->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project ready. Let\'s start.')]);

        return to_route('projects.show', ['project' => $project->slug]);
    }
}
```

`UpdateProjectSetupRequest`:
```php
public function authorize(): bool
{
    return $this->user()->can('update', $this->route('project'));
}

public function rules(): array
{
    /** @var ProjectSetupStep $step */
    $step = $this->route('step');

    return ($step instanceof ProjectSetupStep ? $step : ProjectSetupStep::from($step))->rules();
}
```

`ProjectLogoController::store(UpdateProjectLogoRequest $request, Workspace $workspace, Project $project, UpdateProjectLogo $action)` → `$action->handle($project, $request->file('logo'))`, toast, `back()`. `UpdateProjectLogoRequest` rules: `'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048']`; authorize `update`.

`TaskController.php`:
```php
public function show(Request $request, Workspace $workspace, Project $project, Task $task, ProjectPageProps $props, TaskDetail $detail): Response
{
    Gate::authorize('view', $task);

    return Inertia::render('projects/tasks/show', [
        ...$props->for($project, $request->user()),
        'task' => $detail->handle($task),
    ]);
}
```

`TaskCompletionController.php`:
```php
public function store(Request $request, Workspace $workspace, Project $project, Task $task, MarkTaskDone $markDone): RedirectResponse
{
    Gate::authorize('update', $task);

    return $this->attempt(fn () => $markDone->handle($task, Actor::user($request->user())), __('Marked as done.'));
}

public function destroy(Request $request, Workspace $workspace, Project $project, Task $task, ReopenTask $reopen): RedirectResponse
{
    Gate::authorize('update', $task);

    return $this->attempt(fn () => $reopen->handle($task, Actor::user($request->user())), __('Reopened.'));
}

private function attempt(callable $callback, string $success): RedirectResponse
{
    try {
        $callback();
        Inertia::flash('toast', ['type' => 'success', 'message' => $success]);
    } catch (InvalidTaskTransition $e) {
        Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
    }

    return back();
}
```

- [ ] **Step 5: Run tests, regenerate Wayfinder**

Run: `php artisan test tests/Feature/Project tests/Feature/Task && php artisan wayfinder:generate`
Expected: PASS; `resources/js/routes/projects/` and `resources/js/actions/App/Domain/Project/...` generated.

- [ ] **Step 6: Commit**

```bash
git add routes app/Domain/Project/Http app/Domain/Task/Http tests/Feature
git commit -m "feat: add project, setup and task routes and controllers"
```

---

### Task 14: Frontend foundation — packages, shadcn components, types, Markdown

**Files:**
- Modify: `package.json` (deps)
- Create (generated): `resources/js/components/ui/{progress,scroll-area,textarea,radio-group,tabs}.tsx`
- Create: `resources/js/types/project.ts`, `resources/js/components/markdown/markdown.tsx`, `resources/js/components/markdown/markdown-editor.tsx`
- Modify: `resources/js/types/index.ts` (export project types)

**Interfaces:**
- Produces:
  - `<Markdown source={string|null} className? />` — sanitized render, GFM, links open in new tab.
  - `<MarkdownEditor name={string} defaultValue={string|null} placeholder? />` — Tiptap editor + hidden `<input name>` holding Markdown, so it works inside Inertia `<Form>`.
  - Types (mirror Task 12/13 props): `ProjectPhase`, `TaskStatus`, `TreeNode`, `TreeGroup`, `ProjectTree`, `ProjectSummary`, `ProjectListItem`, `TaskDetail`, `TaskActionDetail`, `ProjectPageProps`.

- [ ] **Step 1: Install packages + shadcn components**

```bash
pnpm add @tiptap/react @tiptap/pm @tiptap/starter-kit @tiptap/markdown @tiptap/extension-placeholder react-markdown remark-gfm rehype-sanitize
pnpm dlx shadcn@latest add progress scroll-area textarea radio-group tabs
```
VERIFY: `@tiptap/markdown` is the official v3 Markdown extension (`editor.getMarkdown()`, `contentType: 'markdown'`). If the installed version lacks it, use `tiptap-markdown` (community) with `editor.storage.markdown.getMarkdown()` and record the choice in `docs/CLAUDE.md`.

- [ ] **Step 2: Types**

`resources/js/types/project.ts`:
```ts
export type ProjectPhase = 'planning' | 'developing' | 'selling';
export type ProjectStatus = 'draft' | 'active' | 'archived';
export type TaskStatus =
    | 'locked'
    | 'todo'
    | 'in_progress'
    | 'blocked'
    | 'awaiting_approval'
    | 'done'
    | 'skipped';

export type Option = { value: string; label: string };
export type PhaseOption = Option & { value: ProjectPhase; description: string };

export type TreeNode = {
    id: string;
    title: string;
    status: TaskStatus;
    progressPct: number;
    depth: number;
    children: TreeNode[];
};

export type TreeGroup = {
    key: string;
    name: string;
    icon: string | null;
    progressPct: number;
    tasks: TreeNode[];
};

export type ProjectTree = { progressPct: number; groups: TreeGroup[] };

export type ProjectSummary = {
    id: string;
    slug: string;
    name: string;
    oneLiner: string | null;
    phase: ProjectPhase;
    phaseLabel: string;
    status: ProjectStatus;
    logoUrl: string | null;
    setupStep: string | null;
};

export type ProjectListItem = Omit<ProjectSummary, 'setupStep'> & { progressPct: number };

export type TaskActionDetail = {
    id: string;
    title: string;
    type: string;
    executor: string;
    status: string;
    instructionsMd: string | null;
    isRequired: boolean;
    prompt: string;
};

export type TaskDetail = {
    id: string;
    title: string;
    summary: string | null;
    bodyMd: string | null;
    status: TaskStatus;
    statusLabel: string;
    progressPct: number;
    depth: number;
    isLeaf: boolean;
    priority: string;
    verification: string;
    completedAt: string | null;
    ancestors: { id: string; title: string }[];
    parent: { id: string; title: string; progressPct: number; status: TaskStatus } | null;
    children: { id: string; title: string; status: TaskStatus; progressPct: number; isLeaf: boolean }[];
    actions: TaskActionDetail[];
};

export type ProjectPageProps = {
    project: ProjectSummary;
    tree: ProjectTree;
    can: { update: boolean };
};
```
Add `export type * from './project';` to `types/index.ts`.

- [ ] **Step 3: Markdown renderer**

```tsx
import ReactMarkdown from 'react-markdown';
import rehypeSanitize from 'rehype-sanitize';
import remarkGfm from 'remark-gfm';
import { cn } from '@/lib/utils';

export function Markdown({ source, className }: { source: string | null; className?: string }) {
    if (!source) {
        return null;
    }

    return (
        <div
            className={cn(
                'space-y-3 text-sm leading-6 [&_a]:text-primary [&_a]:underline [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_h2]:mt-6 [&_h2]:text-base [&_h2]:font-semibold [&_h3]:font-semibold [&_li]:ml-5 [&_ol]:list-decimal [&_ul]:list-disc',
                className,
            )}
        >
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                rehypePlugins={[rehypeSanitize]}
                components={{
                    a: ({ node: _node, ...props }) => (
                        <a {...props} target="_blank" rel="noreferrer noopener" />
                    ),
                }}
            >
                {source}
            </ReactMarkdown>
        </div>
    );
}
```

- [ ] **Step 4: Tiptap Markdown editor**

```tsx
import { Markdown as MarkdownExtension } from '@tiptap/markdown';
import Placeholder from '@tiptap/extension-placeholder';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { Bold, Heading2, Italic, List, ListOrdered } from 'lucide-react';
import { useState } from 'react';
import { Toggle } from '@/components/ui/toggle';

export function MarkdownEditor({
    name,
    defaultValue,
    placeholder,
}: {
    name: string;
    defaultValue: string | null;
    placeholder?: string;
}) {
    const [markdown, setMarkdown] = useState(defaultValue ?? '');

    const editor = useEditor({
        extensions: [StarterKit, MarkdownExtension, Placeholder.configure({ placeholder })],
        content: defaultValue ?? '',
        contentType: 'markdown',
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: 'min-h-32 rounded-b-md border border-t-0 px-3 py-2 text-sm focus:outline-none [&_h2]:text-base [&_h2]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5',
            },
        },
        onUpdate: ({ editor }) => setMarkdown(editor.getMarkdown()),
    });

    const tools = [
        { icon: Bold, label: 'Bold', active: 'bold', run: () => editor?.chain().focus().toggleBold().run() },
        { icon: Italic, label: 'Italic', active: 'italic', run: () => editor?.chain().focus().toggleItalic().run() },
        { icon: Heading2, label: 'Heading', active: 'heading', run: () => editor?.chain().focus().toggleHeading({ level: 2 }).run() },
        { icon: List, label: 'Bullet list', active: 'bulletList', run: () => editor?.chain().focus().toggleBulletList().run() },
        { icon: ListOrdered, label: 'Numbered list', active: 'orderedList', run: () => editor?.chain().focus().toggleOrderedList().run() },
    ];

    return (
        <div>
            <div className="flex gap-1 rounded-t-md border bg-muted/40 p-1">
                {tools.map((tool) => (
                    <Toggle
                        key={tool.label}
                        size="sm"
                        aria-label={tool.label}
                        pressed={editor?.isActive(tool.active) ?? false}
                        onPressedChange={tool.run}
                    >
                        <tool.icon className="size-4" />
                    </Toggle>
                ))}
            </div>
            <EditorContent editor={editor} />
            <input type="hidden" name={name} value={markdown} />
        </div>
    );
}
```

- [ ] **Step 5: Check**

Run: `pnpm run types:check && pnpm run check`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add package.json pnpm-lock.yaml resources/js/components/ui resources/js/components/markdown resources/js/types
git commit -m "feat: add markdown renderer, tiptap editor and project types"
```

---

### Task 15: Projects index + "Add project" (phase picker) + sidebar link

**Files:**
- Create: `resources/js/pages/projects/index.tsx`, `resources/js/pages/projects/create.tsx`, `resources/js/components/project/phase-picker.tsx`
- Modify: `resources/js/components/app-sidebar.tsx` (add "Projects" nav item)

**Interfaces:**
- Consumes: routes `@/routes/projects` (`index`, `create`, `show`), `@/actions/App/Domain/Project/Http/Controllers/ProjectController` (`store`), types `ProjectListItem`, `PhaseOption`.
- Produces: `<PhasePicker phases={PhaseOption[]} name="phase" />` — radio cards (shadcn `RadioGroup`), icons: planning `Lightbulb`, developing `Hammer`, selling `Rocket`.

- [ ] **Step 1: Sidebar item**

In `app-sidebar.tsx` add after Dashboard:
```tsx
import { FolderKanban } from 'lucide-react';
import { index as projectsIndex } from '@/routes/projects';
// ...
{ title: 'Projects', href: projectsIndex(), icon: FolderKanban },
```

- [ ] **Step 2: Phase picker**

```tsx
import { Hammer, Lightbulb, Rocket } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import type { PhaseOption, ProjectPhase } from '@/types';

const icons: Record<ProjectPhase, typeof Lightbulb> = {
    planning: Lightbulb,
    developing: Hammer,
    selling: Rocket,
};

export function PhasePicker({ phases, name }: { phases: PhaseOption[]; name: string }) {
    return (
        <RadioGroup name={name} defaultValue={phases[0]?.value} className="grid gap-3 md:grid-cols-3">
            {phases.map((phase) => {
                const Icon = icons[phase.value];

                return (
                    <Label
                        key={phase.value}
                        htmlFor={`phase-${phase.value}`}
                        className="flex cursor-pointer flex-col items-start gap-3 rounded-xl border p-4 font-normal has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5"
                    >
                        <div className="flex w-full items-center justify-between">
                            <Icon className="size-5" />
                            <RadioGroupItem id={`phase-${phase.value}`} value={phase.value} />
                        </div>
                        <span className="font-medium">{phase.label}</span>
                        <span className="text-sm text-muted-foreground">{phase.description}</span>
                    </Label>
                );
            })}
        </RadioGroup>
    );
}
```

- [ ] **Step 3: Create page**

```tsx
import { Form, Head } from '@inertiajs/react';
import ProjectController from '@/actions/App/Domain/Project/Http/Controllers/ProjectController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PhasePicker } from '@/components/project/phase-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index } from '@/routes/projects';
import type { PhaseOption } from '@/types';

export default function CreateProject({ phases }: { phases: PhaseOption[] }) {
    return (
        <>
            <Head title="New project" />
            <div className="mx-auto w-full max-w-3xl space-y-8 p-4 md:p-8">
                <Heading title="New project" description="Where are you right now? We build your plan from this." />
                <Form {...ProjectController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label>Phase</Label>
                                <PhasePicker phases={phases} name="phase" />
                                <InputError message={errors.phase} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Project name</Label>
                                <Input id="name" name="name" required maxLength={120} placeholder="Acme Rockets" />
                                <InputError message={errors.name} />
                            </div>
                            <Button type="submit" disabled={processing} data-test="create-project-button">
                                Create draft project
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateProject.layout = {
    breadcrumbs: [
        { title: 'Projects', href: index() },
        { title: 'New', href: create() },
    ],
};
```

- [ ] **Step 4: Index page**

Grid of shadcn `Card`s: logo (`WorkspaceAvatar`-style fallback initials via `useInitials`), name, one-liner, `Badge` phase, `Badge variant="outline"` "Draft" when draft, `Progress value={progressPct}`, link to `show({ project: slug })`. Header with `Button asChild` → `create()` when `can.create`. Empty state: centered text "No projects yet" + same button.

```tsx
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { create, index, show } from '@/routes/projects';
import type { ProjectListItem } from '@/types';

export default function ProjectsIndex({ projects, can }: { projects: ProjectListItem[]; can: { create: boolean } }) {
    const addButton = can.create && (
        <Button asChild>
            <Link href={create()}>
                <Plus /> New project
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Projects" />
            <div className="space-y-6 p-4 md:p-8">
                <div className="flex items-start justify-between gap-4">
                    <Heading title="Projects" description="Each project is one company or product." />
                    {projects.length > 0 && addButton}
                </div>
                {projects.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">No projects yet.</p>
                        {addButton}
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {projects.map((project) => (
                            <Link key={project.id} href={show({ project: project.slug })} className="block">
                                <Card className="h-full transition hover:border-primary">
                                    <CardHeader className="flex-row items-center gap-3">
                                        {project.logoUrl && <img src={project.logoUrl} alt="" className="size-10 rounded-md object-cover" />}
                                        <div className="min-w-0">
                                            <CardTitle className="truncate">{project.name}</CardTitle>
                                            <p className="truncate text-sm text-muted-foreground">{project.oneLiner}</p>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        <div className="flex gap-2">
                                            <Badge>{project.phaseLabel}</Badge>
                                            {project.status === 'draft' && <Badge variant="outline">Draft</Badge>}
                                        </div>
                                        <Progress value={project.progressPct} aria-label={`${project.progressPct}% done`} />
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ProjectsIndex.layout = { breadcrumbs: [{ title: 'Projects', href: index() }] };
```

- [ ] **Step 5: Check + manual smoke**

Run: `pnpm run types:check && pnpm run check && composer dev` → log in, open Projects, create a project per phase.
Expected: redirect to `/…/projects/<slug>/setup/identity` (page arrives in Task 16 — a missing-page error here is expected until then).

- [ ] **Step 6: Commit**

```bash
git add resources/js
git commit -m "feat: projects list and phase picker"
```

---

### Task 16: Setup wizard page

**Files:**
- Create: `resources/js/pages/projects/setup.tsx`, `resources/js/components/project/setup-steps.tsx`

**Interfaces:**
- Consumes: `@/actions/App/Domain/Project/Http/Controllers/ProjectSetupController` (`update`), `ProjectLogoController` (`store`), `@/routes/projects/setup` (`edit`), `@/routes/projects` (`show`); props from Task 13.
- Produces: `<SetupSteps steps current projectSlug />` — horizontal stepper; completed steps are links (`edit({project, step})`).

- [ ] **Step 1: Stepper**

```tsx
import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import { edit } from '@/routes/projects/setup';
import type { Option } from '@/types';

export function SetupSteps({ steps, current, projectSlug }: { steps: Option[]; current: string; projectSlug: string }) {
    const currentIndex = steps.findIndex((s) => s.value === current);

    return (
        <ol className="flex flex-wrap items-center gap-2 text-sm">
            {steps.map((step, i) => (
                <li key={step.value} className="flex items-center gap-2">
                    <Link
                        href={edit({ project: projectSlug, step: step.value })}
                        className={cn(
                            'flex items-center gap-2 rounded-full border px-3 py-1',
                            i === currentIndex && 'border-primary bg-primary text-primary-foreground',
                            i < currentIndex && 'text-muted-foreground',
                        )}
                    >
                        {i < currentIndex ? <Check className="size-3" /> : <span>{i + 1}</span>}
                        {step.label}
                    </Link>
                    {i < steps.length - 1 && <span className="text-muted-foreground">—</span>}
                </li>
            ))}
        </ol>
    );
}
```

- [ ] **Step 2: Wizard page**

One page, switch on `step`. All fields are plain inputs inside `<Form {...ProjectSetupController.update.form({ project: project.slug, step })}>` (method PATCH is set by Wayfinder's `.form()`).

- identity: `name` Input, `one_liner` Input (`maxLength=140`, live counter), `description_md` `<MarkdownEditor name="description_md" />`, `website_url` Input type url; plus a separate small `<Form {...ProjectLogoController.store.form({ project: project.slug })} encType="multipart/form-data">` with `<Input type="file" name="logo" accept="image/*" />` + preview of `logoUrl`.
- business: shadcn `Select name="business_model"` and `Select name="stage"` from `options`, `industry`, `pricing_model` Inputs.
- market: `primary_market` Input (`maxLength=2`, uppercase via `onChange` → `e.target.value = e.target.value.toUpperCase()`), `target_customer`, `problem_statement`, `solution_summary` `Textarea`s.
- goals: client list state `goals: {title, metric, target, due}[]` (start from `project.goals`), each row 4 inputs named `goals[${i}][title]` etc., "Add goal" (max 10) and remove buttons; when empty render `<input type="hidden" name="goals" value="" />` so `goals` is present (backend `present|array` — send `goals[]` empty via hidden `name="goals[]"` is wrong; instead use `transform={(data) => ({ ...data, goals: data.goals ?? [] })}` on `<Form>`).

Footer: "Back" link (previous step) + "Save and continue" / on goals "Finish setup". Show `InputError` for every field (`errors['goals.0.title']` for rows). A "Skip for now — open project" link to `show({ project: slug })` (draft stays draft).

```tsx
SetupPage.layout = (props: { project: { slug: string; name: string } }) => ({
    breadcrumbs: [
        { title: 'Projects', href: index() },
        { title: props.project.name, href: show({ project: props.project.slug }) },
        { title: 'Setup', href: '#' },
    ],
});
```
(If Inertia v3 static `layout` objects cannot read props, set breadcrumbs inside the page via `setLayoutProps` — check the inertia-react-development skill.)

- [ ] **Step 3: Check + manual smoke**

Run: `pnpm run types:check && pnpm run check`, then in the browser walk all 4 steps; submit goals with an empty `primary_market` earlier → land back on market with an error.
Expected: after "Finish setup" you land on `/…/projects/<slug>` with toast "Project ready".

- [ ] **Step 4: Commit**

```bash
git add resources/js
git commit -m "feat: project setup wizard"
```

---

### Task 17: Project layout — task-tree sidebar + overview page

**Files:**
- Create: `resources/js/layouts/project-layout.tsx`, `resources/js/components/project/{project-sidebar.tsx,task-tree.tsx,task-status-icon.tsx,project-progress.tsx}`, `resources/js/pages/projects/overview.tsx`
- Modify: `resources/js/app.tsx` (layout switch)

**Interfaces:**
- Consumes: `ProjectPageProps` via `usePage<ProjectPageProps>().props`; routes `@/routes/projects` (`index`, `show`), `@/routes/projects/tasks` (`show`), `@/routes/projects/setup` (`edit`).
- Produces:
  - `ProjectLayout` — `AppShell variant="sidebar"` + `ProjectSidebar` + `AppContent`; header row = breadcrumbs (left) + `<ProjectProgress value>` (right).
  - `<TaskTree groups activeTaskId? />` — `SidebarGroup` per category (label + group %), `Collapsible` nodes, `TaskStatusIcon`, active item highlighted, auto-expands ancestors of the active task.
  - `<TaskStatusIcon status />` — `done` CheckCircle2 (green), `in_progress` CircleDot, `locked` Lock, `skipped` CircleSlash, others Circle.
  - `<ProjectProgress value label? />` — `Progress` + `NN%` text, `w-40`.

- [ ] **Step 1: Layout switch in `app.tsx`**

```tsx
import ProjectLayout from '@/layouts/project-layout';
// in layout switch, before default:
case name === 'projects/overview':
case name.startsWith('projects/tasks/'):
    return ProjectLayout;
```

- [ ] **Step 2: Status icon + progress**

```tsx
import { CheckCircle2, Circle, CircleDot, CircleSlash, Lock } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { TaskStatus } from '@/types';

export function TaskStatusIcon({ status, className }: { status: TaskStatus; className?: string }) {
    const props = { className: cn('size-4 shrink-0', className), 'aria-label': status.replace('_', ' ') };

    switch (status) {
        case 'done':
            return <CheckCircle2 {...props} className={cn(props.className, 'text-emerald-600')} />;
        case 'in_progress':
            return <CircleDot {...props} className={cn(props.className, 'text-amber-500')} />;
        case 'locked':
            return <Lock {...props} className={cn(props.className, 'text-muted-foreground')} />;
        case 'skipped':
            return <CircleSlash {...props} className={cn(props.className, 'text-muted-foreground')} />;
        default:
            return <Circle {...props} className={cn(props.className, 'text-muted-foreground')} />;
    }
}
```

```tsx
import { Progress } from '@/components/ui/progress';

export function ProjectProgress({ value, label = 'Progress' }: { value: number; label?: string }) {
    return (
        <div className="flex items-center gap-3" aria-label={`${label}: ${value}%`}>
            <span className="hidden text-xs text-muted-foreground sm:inline">{label}</span>
            <Progress value={value} className="w-32 md:w-40" />
            <span className="w-10 text-right text-sm font-medium tabular-nums">{value}%</span>
        </div>
    );
}
```

- [ ] **Step 3: Task tree**

```tsx
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
} from '@/components/ui/sidebar';
import { TaskStatusIcon } from '@/components/project/task-status-icon';
import { show } from '@/routes/projects/tasks';
import type { TreeGroup, TreeNode } from '@/types';

function containsTask(node: TreeNode, id?: string): boolean {
    return !!id && (node.id === id || node.children.some((c) => containsTask(c, id)));
}

function Node({ node, projectSlug, activeTaskId }: { node: TreeNode; projectSlug: string; activeTaskId?: string }) {
    const link = (
        <SidebarMenuButton asChild isActive={node.id === activeTaskId} size="sm">
            <Link href={show({ project: projectSlug, task: node.id })} prefetch>
                <TaskStatusIcon status={node.status} />
                <span className="truncate">{node.title}</span>
            </Link>
        </SidebarMenuButton>
    );

    if (node.children.length === 0) {
        return <SidebarMenuItem>{link}</SidebarMenuItem>;
    }

    return (
        <Collapsible asChild defaultOpen={containsTask(node, activeTaskId)}>
            <SidebarMenuItem>
                <div className="flex items-center">
                    <CollapsibleTrigger className="p-1 [&[data-state=open]>svg]:rotate-90" aria-label={`Toggle ${node.title}`}>
                        <ChevronRight className="size-3 transition-transform" />
                    </CollapsibleTrigger>
                    <div className="min-w-0 flex-1">{link}</div>
                </div>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {node.children.map((child) => (
                            <Node key={child.id} node={child} projectSlug={projectSlug} activeTaskId={activeTaskId} />
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

export function TaskTree({ groups, projectSlug, activeTaskId }: { groups: TreeGroup[]; projectSlug: string; activeTaskId?: string }) {
    return groups.map((group) => (
        <SidebarGroup key={group.key}>
            <SidebarGroupLabel className="flex justify-between">
                <span>{group.name}</span>
                <span className="tabular-nums">{group.progressPct}%</span>
            </SidebarGroupLabel>
            <SidebarMenu>
                {group.tasks.map((node) => (
                    <Node key={node.id} node={node} projectSlug={projectSlug} activeTaskId={activeTaskId} />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    ));
}
```

- [ ] **Step 4: Project sidebar + layout**

`project-sidebar.tsx`: `Sidebar collapsible="offcanvas" variant="inset"`; header = back link "← All projects" (`index()`), project logo/initials + name + phase `Badge` (links to `show({project})`), "Finish setup" `Button size="sm"` when `project.setupStep` (→ `edit({project, step: setupStep})`); content = `<ScrollArea><TaskTree … /></ScrollArea>`; footer = `<NavUser />`. Active task id = `usePage().props.task?.id`.

`project-layout.tsx`:
```tsx
import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ProjectProgress } from '@/components/project/project-progress';
import { ProjectSidebar } from '@/components/project/project-sidebar';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useWorkspaceUrlDefaults } from '@/hooks/use-workspace-url-defaults';
import { index, show } from '@/routes/projects';
import { show as showTask } from '@/routes/projects/tasks';
import type { BreadcrumbItem, ProjectPageProps, TaskDetail } from '@/types';

export default function ProjectLayout({ children }: { children: React.ReactNode }) {
    useWorkspaceUrlDefaults();
    const { project, tree, task } = usePage<ProjectPageProps & { task?: TaskDetail }>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: index() },
        { title: project.name, href: show({ project: project.slug }) },
        ...(task?.ancestors ?? []).map((a) => ({ title: a.title, href: showTask({ project: project.slug, task: a.id }) })),
        ...(task ? [{ title: task.title, href: showTask({ project: project.slug, task: task.id }) }] : []),
    ];

    return (
        <AppShell variant="sidebar">
            <ProjectSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <header className="flex h-16 shrink-0 items-center justify-between gap-4 border-b px-4">
                    <div className="flex min-w-0 items-center gap-2">
                        <SidebarTrigger className="-ml-1" />
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                    <ProjectProgress value={task ? task.progressPct : tree.progressPct} label={task ? 'Task' : 'Project'} />
                </header>
                {children}
            </AppContent>
        </AppShell>
    );
}
```

- [ ] **Step 5: Overview page**

`projects/overview.tsx`: draft banner (`Alert`: "Setup not finished" + link to setup step); hero with project name, one-liner, phase badge; "Continue: {nextTask.title}" primary `Button` → task page; grid of category `Card`s (name, `Progress` = group %, count done/total roots). No tree here — the sidebar holds it.

- [ ] **Step 6: Check + manual smoke**

Run: `pnpm run types:check && pnpm run check`; browser: open a project → sidebar shows grouped tree, progress top-right, collapse/expand works, mobile `SidebarTrigger` opens the sheet.

- [ ] **Step 7: Commit**

```bash
git add resources/js
git commit -m "feat: project layout with task tree sidebar and overview"
```

---

### Task 18: Task detail page (parent + subtask views, prompts)

**Files:**
- Create: `resources/js/pages/projects/tasks/show.tsx`, `resources/js/components/task/{task-header.tsx,parent-task-card.tsx,subtask-list.tsx,action-card.tsx,prompt-buttons.tsx}`

**Interfaces:**
- Consumes: `TaskDetail`, `ProjectPageProps`; `@/actions/App/Domain/Task/Http/Controllers/TaskCompletionController` (`store`, `destroy`); `@/routes/projects/tasks` (`show`); `useClipboard`.
- Produces:
  - `<TaskHeader task canUpdate projectSlug />` — title, status badge, priority badge, summary; leaf + `canUpdate`: "Mark as done" / "Reopen" button (`<Form {...TaskCompletionController.store.form({project, task})}>` / `destroy`). Locked → disabled button + tooltip "Finish dependencies first".
  - `<ParentTaskCard parent projectSlug />` — shown when `task.parent` exists (subtask view): compact card at the top with parent title, status icon, `Progress`, link back.
  - `<SubtaskList items projectSlug canUpdate />` — rows: checkbox (leaf only; toggles completion with `router.post/delete`, `preserveScroll: true`, optimistic: local checked state reverts on error), status icon, title link, `progressPct` for non-leaf children.
  - `<ActionCard action />` — title, `Badge` type + executor, required marker, `<Markdown source={instructionsMd} />`, `<PromptButtons prompt />`.
  - `<PromptButtons prompt />` — "Copy prompt" (`useClipboard`, toast via `sonner` "Prompt copied"), "Preview" (`Dialog` showing the prompt in `<pre>`), "Open in Claude" button disabled with tooltip "Coming soon — connect Claude" (enabled in P1).

- [ ] **Step 1: Page composition**

```tsx
import { Head } from '@inertiajs/react';
import { Markdown } from '@/components/markdown/markdown';
import { ActionCard } from '@/components/task/action-card';
import { ParentTaskCard } from '@/components/task/parent-task-card';
import { SubtaskList } from '@/components/task/subtask-list';
import { TaskHeader } from '@/components/task/task-header';
import type { ProjectPageProps, TaskDetail } from '@/types';

export default function TaskShow({ project, can, task }: ProjectPageProps & { task: TaskDetail }) {
    return (
        <>
            <Head title={task.title} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-8">
                {task.parent && <ParentTaskCard parent={task.parent} projectSlug={project.slug} />}
                <TaskHeader task={task} canUpdate={can.update} projectSlug={project.slug} />
                <Markdown source={task.bodyMd} />
                {task.children.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="font-semibold">Subtasks</h2>
                        <SubtaskList items={task.children} projectSlug={project.slug} canUpdate={can.update} />
                    </section>
                )}
                {task.actions.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="font-semibold">How to get it done</h2>
                        {task.actions.map((action) => (
                            <ActionCard key={action.id} action={action} />
                        ))}
                    </section>
                )}
            </div>
        </>
    );
}
```

- [ ] **Step 2: Prompt buttons**

```tsx
import { Copy, ExternalLink, Eye } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';

export function PromptButtons({ prompt }: { prompt: string }) {
    const [, copy] = useClipboard();

    return (
        <div className="flex flex-wrap gap-2">
            <Button
                size="sm"
                onClick={async () => ((await copy(prompt)) ? toast.success('Prompt copied') : toast.error('Copy failed'))}
            >
                <Copy /> Copy prompt
            </Button>
            <Dialog>
                <DialogTrigger asChild>
                    <Button size="sm" variant="outline">
                        <Eye /> Preview
                    </Button>
                </DialogTrigger>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Prompt</DialogTitle>
                    </DialogHeader>
                    <pre className="max-h-[60vh] overflow-auto whitespace-pre-wrap rounded-md bg-muted p-3 text-xs">{prompt}</pre>
                </DialogContent>
            </Dialog>
            <Tooltip>
                <TooltipTrigger asChild>
                    <span>
                        <Button size="sm" variant="outline" disabled>
                            <ExternalLink /> Open in Claude
                        </Button>
                    </span>
                </TooltipTrigger>
                <TooltipContent>Coming soon — connect Claude</TooltipContent>
            </Tooltip>
        </div>
    );
}
```

- [ ] **Step 3: Subtask list with optimistic checkbox**

```tsx
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import TaskCompletionController from '@/actions/App/Domain/Task/Http/Controllers/TaskCompletionController';
import { TaskStatusIcon } from '@/components/project/task-status-icon';
import { Checkbox } from '@/components/ui/checkbox';
import { show } from '@/routes/projects/tasks';
import type { TaskDetail } from '@/types';

type Item = TaskDetail['children'][number];

function Row({ item, projectSlug, canUpdate }: { item: Item; projectSlug: string; canUpdate: boolean }) {
    const [checked, setChecked] = useState(item.status === 'done');
    const args = { project: projectSlug, task: item.id };

    const toggle = (next: boolean) => {
        setChecked(next);
        router.visit(next ? TaskCompletionController.store(args) : TaskCompletionController.destroy(args), {
            preserveScroll: true,
            onError: () => setChecked(!next),
        });
    };

    return (
        <li className="flex items-center gap-3 rounded-md border p-3">
            {item.isLeaf ? (
                <Checkbox
                    checked={checked}
                    disabled={!canUpdate || item.status === 'locked'}
                    onCheckedChange={(v) => toggle(v === true)}
                    aria-label={`Mark ${item.title} as done`}
                />
            ) : (
                <TaskStatusIcon status={item.status} />
            )}
            <Link href={show(args)} className="min-w-0 flex-1 truncate hover:underline">
                {item.title}
            </Link>
            {!item.isLeaf && <span className="text-xs tabular-nums text-muted-foreground">{item.progressPct}%</span>}
        </li>
    );
}

export function SubtaskList({ items, projectSlug, canUpdate }: { items: Item[]; projectSlug: string; canUpdate: boolean }) {
    return (
        <ul className="space-y-2">
            {items.map((item) => (
                <Row key={item.id} item={item} projectSlug={projectSlug} canUpdate={canUpdate} />
            ))}
        </ul>
    );
}
```

The completion controller returns `back()` with a toast. A domain refusal (locked/parent) comes back as an error *toast*, not a validation error, so `onError` will not fire; the server state wins on reload because `checked` is re-initialised from props through `key={item.id + item.status}` on `<Row>` — use that key instead of `item.id`.

- [ ] **Step 4: Header, parent card, action card**

Implement as described in Interfaces with shadcn `Card`, `Badge`, `Button`, `Progress`, `Tooltip`; `<Form>` for complete/reopen with `options={{ preserveScroll: true }}`.

- [ ] **Step 5: Check + manual smoke**

Run: `pnpm run types:check && pnpm run check`; browser:
1. Open a root task → body, subtasks, actions, prompt copy works (paste into a text editor).
2. Click a subtask → parent card + breadcrumb on top, same layout below.
3. Check a subtask → tree icon + top-right progress update without full reload.
4. Check a locked subtask → disabled.

- [ ] **Step 6: Commit**

```bash
git add resources/js
git commit -m "feat: task page with subtasks, progress and copy prompt"
```

---

### Task 19: Docs + full gate

**Files:**
- Modify: `docs/CLAUDE.md` (folder layout → module per domain; Postgres; Tiptap decided; `catalog:import`), `docs/DATA_MODEL.md` (add `projects.phase`, `projects.status` `draft`, `projects.activated_at`, `content_hash` on `catalog_tasks`/`prompt_templates`/`packs`, `packs.audience.phase` + one default per phase), `docs/PROJECT_CONTEXT.md` §7.1 (phase → default pack → draft → wizard)
- Modify: `database/seeders/DatabaseSeeder.php` (verified in Task 5)

- [ ] **Step 1: Update docs** with the exact changes listed above (copy table rows from Tasks 4, 6, 9).

- [ ] **Step 2: Full gate**

Run: `composer ci:check`
Expected: `pnpm run check`, `pnpm run types:check`, Pint, PHPStan, Pest — all green. Fix any Larastan errors by adding generics/`@property` docblocks, not by baseline.

- [ ] **Step 3: Fresh-install smoke**

Run: `php artisan migrate:fresh --seed && composer dev` → register a new user → personal workspace → Projects → New project (selling) → wizard → overview → complete all subtasks of one task → parent shows done and 100%.

- [ ] **Step 4: Commit**

```bash
git add docs database/seeders
git commit -m "docs: align spec with P0 decisions"
```

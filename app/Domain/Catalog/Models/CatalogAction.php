<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
use Database\Factories\CatalogActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $catalog_task_id
 * @property string $key
 * @property string $title
 * @property ActionType $type
 * @property Executor $executor
 * @property string|null $instructions_md
 * @property int|null $prompt_template_id
 * @property array<string, mixed>|null $config
 * @property bool $is_required
 * @property bool $requires_approval
 * @property int $sort_order
 * @property int $version
 * @property-read CatalogTask $task
 * @property-read PromptTemplate|null $promptTemplate
 */
#[Fillable(['catalog_task_id', 'key', 'title', 'type', 'executor', 'instructions_md', 'prompt_template_id', 'config', 'is_required', 'requires_approval', 'sort_order', 'version'])]
#[UseFactory(CatalogActionFactory::class)]
class CatalogAction extends Model
{
    /** @use HasFactory<CatalogActionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ActionType::class,
            'executor' => Executor::class,
            'config' => 'array',
            'is_required' => 'boolean',
            'requires_approval' => 'boolean',
        ];
    }

    /** @return BelongsTo<CatalogTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(CatalogTask::class, 'catalog_task_id');
    }

    /** @return BelongsTo<PromptTemplate, $this> */
    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }
}

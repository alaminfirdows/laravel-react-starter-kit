<?php

namespace App\Domain\Project\Models;

use App\Domain\Knowledge\Models\Competitor;
use App\Domain\Knowledge\Models\Decision;
use App\Domain\Knowledge\Models\Interview;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Enums\BusinessModel;
use App\Domain\Project\Enums\LegalEntityStatus;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Enums\Stage;
use App\Domain\Project\Policies\ProjectPolicy;
use App\Domain\Task\Models\Approval;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Concerns\BelongsToWorkspace;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $owner_id
 * @property string $name
 * @property string $slug
 * @property ProjectPhase $phase
 * @property ProjectStatus $status
 * @property int $progress_pct
 * @property string|null $one_liner
 * @property string|null $description_md
 * @property string|null $website_url
 * @property string|null $primary_domain
 * @property BusinessModel|null $business_model
 * @property string|null $industry
 * @property Stage|null $stage
 * @property string|null $pricing_model
 * @property string|null $revenue_band
 * @property string|null $primary_market
 * @property list<string>|null $target_markets
 * @property list<string>|null $languages
 * @property string|null $target_customer
 * @property string|null $problem_statement
 * @property string|null $solution_summary
 * @property LegalEntityStatus|null $legal_entity_status
 * @property string|null $entity_type
 * @property string|null $jurisdiction
 * @property CarbonImmutable|null $founded_on
 * @property int|null $team_size
 * @property string|null $timezone
 * @property string|null $currency
 * @property list<array{title: string, metric?: string|null, target?: string|null, due?: string|null}>|null $goals
 * @property array<string, mixed>|null $tech
 * @property string|null $context_snapshot_md
 * @property array<string, mixed>|null $settings
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string|null $logo_url
 * @property-read ProjectBrand|null $brand
 * @property-read User $owner
 */
#[Fillable(['name', 'one_liner', 'description_md', 'description_doc', 'website_url', 'primary_domain', 'business_model', 'industry', 'stage', 'pricing_model', 'revenue_band', 'primary_market', 'target_markets', 'languages', 'target_customer', 'problem_statement', 'solution_summary', 'legal_entity_status', 'entity_type', 'jurisdiction', 'founded_on', 'team_size', 'timezone', 'currency', 'goals', 'tech'])]
#[UseFactory(ProjectFactory::class)]
#[UsePolicy(ProjectPolicy::class)]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToWorkspace, HasFactory, HasUlids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'progress_pct' => 0,
    ];

    protected function casts(): array
    {
        return [
            'phase' => ProjectPhase::class,
            'status' => ProjectStatus::class,
            'business_model' => BusinessModel::class,
            'stage' => Stage::class,
            'legal_entity_status' => LegalEntityStatus::class,
            'target_markets' => 'array',
            'languages' => 'array',
            'goals' => 'array',
            'tech' => 'array',
            'settings' => 'array',
            'meta' => 'array',
            'description_doc' => 'array',
            'founded_on' => 'immutable_date',
            'activated_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isDraft(): bool
    {
        return $this->status === ProjectStatus::Draft;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<Approval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    /**
     * @return HasMany<KnowledgeDocument, $this>
     */
    public function knowledgeDocuments(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    /**
     * @return HasMany<Decision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class);
    }

    /**
     * @return HasMany<Interview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    /**
     * @return HasMany<Competitor, $this>
     */
    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class);
    }

    /**
     * @return HasOne<ProjectBrand, $this>
     */
    public function brand(): HasOne
    {
        return $this->hasOne(ProjectBrand::class);
    }

    /**
     * @return HasMany<ProjectPack, $this>
     */
    public function packs(): HasMany
    {
        return $this->hasMany(ProjectPack::class);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->brand?->logo?->url());
    }
}

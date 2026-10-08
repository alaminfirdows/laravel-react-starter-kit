<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Mirror of `resources/skills/<key>/SKILL.md`; the file is the source of truth.
 *
 * @property int $id
 * @property string $key
 * @property string $title
 * @property string $description
 * @property string $version
 * @property string $source_path
 * @property bool $in_plugin
 * @property bool $in_app_agents
 * @property string $content_hash
 * @property-read Pivot|null $pivot Set when loaded through CatalogTask
 */
#[Fillable(['key', 'title', 'description', 'version', 'source_path', 'in_plugin', 'in_app_agents', 'content_hash'])]
#[UseFactory(SkillFactory::class)]
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'in_plugin' => 'boolean',
            'in_app_agents' => 'boolean',
        ];
    }
}

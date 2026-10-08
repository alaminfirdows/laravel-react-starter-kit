<?php

namespace Tests\Fixtures;

use App\Domain\Workspace\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant model used to test BelongsToWorkspace.
 */
class TenantNote extends Model
{
    use BelongsToWorkspace, HasUlids;

    protected $table = 'tenant_notes';

    protected $guarded = [];

    public static function createTable(): void
    {
        Schema::create('tenant_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('body');
            $table->timestamps();
        });
    }
}

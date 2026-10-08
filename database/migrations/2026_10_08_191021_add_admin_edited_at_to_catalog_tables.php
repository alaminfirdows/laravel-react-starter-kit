<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin UI edits not yet exported to YAML (`catalog:export` clears it).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['catalog_tasks', 'prompt_templates', 'packs'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('admin_edited_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('admin_edited_at');
            });
        }
    }
};

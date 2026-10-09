<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog field values as last copied into the task. Base of the 3-way
 * diff in DiffCatalogVersion: tells founder edits apart from catalog edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->jsonb('catalog_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('catalog_snapshot');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catalog admin events are global: no workspace, and subjects with integer ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE activity_log ALTER COLUMN workspace_id DROP NOT NULL');
        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE varchar(36)');
    }

    public function down(): void
    {
        // Destructive by design: global (catalog) rows cannot exist with a NOT NULL workspace_id.
        DB::statement('DELETE FROM activity_log WHERE workspace_id IS NULL');
        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE char(26)');
        DB::statement('ALTER TABLE activity_log ALTER COLUMN workspace_id SET NOT NULL');
    }
};

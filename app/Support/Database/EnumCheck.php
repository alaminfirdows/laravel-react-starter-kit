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

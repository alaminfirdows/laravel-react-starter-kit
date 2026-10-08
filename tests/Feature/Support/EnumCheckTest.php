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

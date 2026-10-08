<?php

namespace App\Domain\Catalog\Support;

use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PromptTemplate;

/**
 * Catalog version rules shared by `catalog:import` and the admin UI:
 * a content change bumps `version`, an unchanged save never does.
 */
final class Versioning
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function hash(array $attributes): string
    {
        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }

    /**
     * Import: save a keyed row when the authored content hash changed.
     * YAML is the source again, so pending admin edits are cleared.
     *
     * @template TModel of CatalogTask|PromptTemplate|Pack
     *
     * @param  TModel  $model  existing row or new instance with `key` set
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $except  hashed but not stored
     * @return TModel
     */
    public static function import(CatalogTask|PromptTemplate|Pack $model, array $attributes, array $except = []): CatalogTask|PromptTemplate|Pack
    {
        $hash = self::hash($attributes);

        if ($model->exists && $model->content_hash === $hash) {
            return $model;
        }

        $model->fill([
            ...array_diff_key($attributes, array_flip($except)),
            'content_hash' => $hash,
            'version' => $model->exists ? $model->version + 1 : 1,
            'admin_edited_at' => null,
        ])->save();

        return $model;
    }

    /**
     * Admin edit: save when a stored field changed. Returns false for a no-op save.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function edit(CatalogTask|PromptTemplate|Pack $model, array $attributes, bool $force = false): bool
    {
        $model->fill($attributes);

        if ($model->exists && ! $force && ! $model->isDirty()) {
            return false;
        }

        $model->fill([
            'content_hash' => $attributes === [] ? $model->content_hash : self::hash($attributes),
            'version' => $model->exists ? $model->version + 1 : 1,
            'admin_edited_at' => now(),
        ])->save();

        return true;
    }
}

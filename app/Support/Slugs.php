<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generates SEO-friendly slugs and guarantees database uniqueness without
 * ever trusting a raw title or a client-supplied slug.
 */
class Slugs
{
    /**
     * Build a unique slug for the given base string.
     *
     * @param  class-string<Model>|Model  $model  model class or instance to check against
     * @param  int|null  $ignoreId  primary key to exclude (used when updating)
     */
    public static function unique(string $base, string|Model $model, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base);
        $lengthLimit = 190;
        if ($slug === '') {
            $slug = 'item';
        }
        $slug = Str::limit($slug, $lengthLimit, '');

        $class = $model instanceof Model ? $model::class : $model;

        $candidate = $slug;
        $suffix = 2;

        while (self::exists($class, $candidate, $ignoreId)) {
            $candidate = Str::limit($slug, $lengthLimit - strlen((string) $suffix) - 1, '').'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected static function exists(string $model, string $candidate, ?int $ignoreId): bool
    {
        return $model::query()
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * Build a slug that only has to be unique inside a scope.
     *
     * Chapter slugs are unique per book, not per site — every book is free to
     * have its own "chapter-1" — so the plain unique() check would be wrong
     * here and would needlessly rename perfectly valid slugs.
     *
     * @param  Closure(string): mixed  $scope  constraint applied to the uniqueness query
     */
    public static function uniqueScoped(string $base, string|Model $model, Closure $scope, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base);
        $lengthLimit = 190;
        if ($slug === '') {
            $slug = 'item';
        }
        $slug = Str::limit($slug, $lengthLimit, '');

        $class = $model instanceof Model ? $model::class : $model;

        $candidate = $slug;
        $suffix = 2;

        while ($class::query()
            ->where('slug', $candidate)
            ->where($scope)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $candidate = Str::limit($slug, $lengthLimit - strlen((string) $suffix) - 1, '').'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}

<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Cache;

use Illuminate\Contracts\Auth\Authenticatable;

interface CacheKeyAndTags
{
    /**
     * Generate the cache key.
     *
     * @param  array<string, mixed>  $args
     * @param  array<int, string|int>  $path
     */
    public function key(?Authenticatable $user, bool $isPrivate, string $parentName, int|string|null $id, string $fieldName, array $args, array $path): string;

    /** Generate a tag for the parent. */
    public function parentTag(string $parentName, int|string|null $id): string;

    /** Generate a tag for the field. */
    public function fieldTag(string $parentName, int|string|null $id, string $fieldName): string;
}

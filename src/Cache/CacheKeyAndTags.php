<?php

namespace Nuwave\Lighthouse\Cache;

use Illuminate\Contracts\Auth\Authenticatable;

interface CacheKeyAndTags
{
    /**
     * Generate the cache key.
     *
     * @param  int|string|null $id
     * @param  array<string, mixed> $args
     * @param  array<int, string> $path
     */
    public function key(?Authenticatable $user, bool $isPrivate, array $args, ResolveInfo $resolveInfo): string;

    /**
     * Generate a tag for the parent.
     *
     * @param  int|string|null $id
     */
    public function parentTag(string $parentName, $id): string;

    /**
     * Generate a tag for the field.
     *
     * @param  int|string|null $id
     */
    public function fieldTag(string $parentName, $id, string $fieldName): string;
}

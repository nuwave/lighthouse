<?php

namespace Nuwave\Lighthouse\Cache;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Auth\Authenticatable;

interface CacheKeyAndTags
{
    /**
     * Generate the cache key.
     *
     * @param  int|string|null $id
     * @param  array<string, mixed> $args
     */
    public function key(?Authenticatable $user, bool $isPrivate, $id, array $args, ResolveInfo $resolveInfo): string;

    /**
     * Generate a tag for the parent.
     *
     * @param  int|string|null $id
     */
    public function parentTag($id, ResolveInfo $resolveInfo, ?string $parentName = null): string;

    /**
     * Generate a tag for the field.
     *
     * @param  int|string|null $id
     */
    public function fieldTag($id, ResolveInfo $resolveInfo, ?string $parentName = null, ?string $fieldName = null): string;
}

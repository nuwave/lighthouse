<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface CacheKeyAndTags
{
    /**
     * @param int|string|null      $id
     * @param array<string, mixed> $args
     */
    public function key(?Authenticatable $user, bool $isPrivate, string $parentName, $id, string $fieldName, array $args): string;

    /**
     * @param int|string|null $id
     *
     * @return array{string, string}
     */
    public function tags(string $parentName, $id, string $fieldName): array;

    /**
     * @param int|string|null $id
     */
    public function parentTag(string $parentName, $id): string;

    /**
     * @param int|string|null $id
     */
    public function fieldTag(string $parentName, $id, string $fieldName): string;
}

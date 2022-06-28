<?php

namespace Nuwave\Lighthouse\Cache;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Auth\Authenticatable;

class CacheKeyAndTagsGenerator implements CacheKeyAndTags
{
    public const PREFIX = 'lighthouse';
    public const SEPARATOR = ':';

    /**
     * @param  int|string|null $id
     * @param  array<string, mixed> $args
     */
    public function key(
        ?Authenticatable $user,
        bool $isPrivate,
        $id,
        array $args,
        ResolveInfo $resolveInfo
    ): string {
        $parentName = $resolveInfo->parentType->name;
        $fieldName = $resolveInfo->fieldName;

        $parts = [self::PREFIX];

        if ($isPrivate && null !== $user) {
            $parts[] = 'auth';
            $parts[] = $user->getAuthIdentifier();
        }

        $parts[] = $parentName;
        $parts[] = $id;
        $parts[] = $fieldName;

        ksort($args);
        foreach ($args as $key => $value) {
            $parts[] = $key;
            $parts[] = is_array($value)
                ? \Safe\json_encode($value)
                : $value;
        }

        return implode(self::SEPARATOR, $parts);
    }

    /**
     * @param  int|string|null $id
     */
    public function parentTag($id, ResolveInfo $resolveInfo, ?string $parentName = null): string
    {
        $parentName = $parentName ?? $resolveInfo->parentType->name;

        return implode(self::SEPARATOR, [
            self::PREFIX,
            $parentName,
            $id,
        ]);
    }

    /**
     * @param  int|string|null $id
     */
    public function fieldTag($id, ResolveInfo $resolveInfo, ?string $parentName = null, ?string $fieldName = null): string
    {
        $parentName = $parentName ?? $resolveInfo->parentType->name;
        $fieldName = $fieldName ?? $resolveInfo->fieldName;

        return implode(self::SEPARATOR, [
            self::PREFIX,
            $parentName,
            $id,
            $fieldName,
        ]);
    }
}

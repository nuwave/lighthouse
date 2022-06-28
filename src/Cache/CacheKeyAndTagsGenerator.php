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
        $parts = [self::PREFIX];

        if ($isPrivate && null !== $user) {
            $parts[] = 'auth';
            $parts[] = $user->getAuthIdentifier();
        }

        $parts[] = $resolveInfo->parentType->name;
        $parts[] = $id;
        $parts[] = $resolveInfo->fieldName;

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
        return implode(self::SEPARATOR, [
            self::PREFIX,
            $parentName ?? $resolveInfo->parentType->name,
            $id,
        ]);
    }

    /**
     * @param  int|string|null $id
     */
    public function fieldTag($id, ResolveInfo $resolveInfo, ?string $parentName = null, ?string $fieldName = null): string
    {
        return implode(self::SEPARATOR, [
            self::PREFIX,
            $parentName ?? $resolveInfo->parentType->name,
            $id,
            $fieldName ?? $resolveInfo->fieldName,
        ]);
    }
}

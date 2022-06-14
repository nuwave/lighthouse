<?php

namespace Nuwave\Lighthouse\Cache;

use Illuminate\Contracts\Auth\Authenticatable;

class CacheKeyAndTagsGenerator implements CacheKeyAndTags
{
    public const PREFIX = 'lighthouse';
    public const SEPARATOR = ':';

    /**
     * @param  int|string|null  $id
     * @param  array<string, mixed>  $args
     */
    public function key(
        ?Authenticatable $user,
        bool $isPrivate,
        string $parentName,
        $id,
        string $fieldName,
        array $args
    ): string {
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
    public function parentTag(string $parentName, $id): string
    {
        return implode(self::SEPARATOR, [
            self::PREFIX,
            $parentName,
            $id,
        ]);
    }

    /**
     * @param  int|string|null $id
     */
    public function fieldTag(string $parentName, $id, string $fieldName): string
    {
        return implode(self::SEPARATOR, [
            self::PREFIX,
            $parentName,
            $id,
            $fieldName,
        ]);
    }
}

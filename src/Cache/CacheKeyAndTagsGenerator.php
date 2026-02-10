<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Cache;

use Illuminate\Contracts\Auth\Authenticatable;

class CacheKeyAndTagsGenerator implements CacheKeyAndTags
{
    public const PREFIX = 'lighthouse';

    public const SEPARATOR = ':';

    /**
     * @param  array<string, mixed>  $args
     * @param  array<int, string|int>  $path
     */
    public function key(
        ?Authenticatable $user,
        bool $isPrivate,
        string $parentName,
        int|string|null $id,
        string $fieldName,
        array $args,
        array $path,
    ): string {
        $parts = [self::PREFIX];

        if ($isPrivate && $user !== null) {
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

    public function parentTag(string $parentName, int|string|null $id): string
    {
        return implode(self::SEPARATOR, [
            self::PREFIX,
            $parentName,
            $id,
        ]);
    }

    public function fieldTag(string $parentName, int|string|null $id, string $fieldName): string
    {
        return implode(self::SEPARATOR, [
            self::PREFIX,
            $parentName,
            $id,
            $fieldName,
        ]);
    }
}

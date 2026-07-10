<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema;

class RootType
{
    public const QUERY = 'Query';

    public const MUTATION = 'Mutation';

    public const SUBSCRIPTION = 'Subscription';

    public static function isRootType(string $typeName): bool
    {
        return in_array(
            $typeName,
            [
                static::QUERY,
                static::MUTATION,
                static::SUBSCRIPTION,
            ],
        );
    }

    /** @return array<int, string> */
    public static function namespaces(string $rootType): array
    {
        $key = match ($rootType) {
            self::QUERY => 'queries',
            self::MUTATION => 'mutations',
            self::SUBSCRIPTION => 'subscriptions',
            default => throw new \InvalidArgumentException("Invalid root type: {$rootType}."),
        };

        return (array) config("lighthouse.namespaces.{$key}");
    }
}

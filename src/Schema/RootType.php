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

    /** @return non-empty-array<string> */
    public static function namespaces(string $rootType): array
    {
        $key = match ($rootType) {
            self::QUERY => 'queries',
            self::MUTATION => 'mutations',
            self::SUBSCRIPTION => 'subscriptions',
            default => throw new \InvalidArgumentException("Invalid root type: {$rootType}."),
        };

        $namespaces = (array) config("lighthouse.namespaces.{$key}");

        foreach ($namespaces as $namespace) {
            if (! is_string($namespace)) {
                throw new \RuntimeException("Non-string value configured for lighthouse.namespaces.{$key}.");
            }
        }

        if ($namespaces === []) {
            throw new \RuntimeException("No namespaces configured for lighthouse.namespaces.{$key}.");
        }

        return array_values($namespaces);
    }
}

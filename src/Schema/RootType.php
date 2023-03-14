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

    /**
     * @return array<int, string>
     */
    public static function defaultNamespaces(string $typeName): array
    {
        return match ($typeName) {
            static::QUERY => (array) config('lighthouse.namespaces.queries'),
            static::MUTATION => (array) config('lighthouse.namespaces.mutations'),
            static::SUBSCRIPTION => (array) config('lighthouse.namespaces.subscriptions'),
            default => [],
        };
    }
}

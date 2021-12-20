<?php

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
            ]
        );
    }

    /**
     * @return array<int, string>
     */
    public static function defaultNamespaces(string $typeName): array
    {
        switch ($typeName) {
            case static::QUERY:
                return (array) config('lighthouse.namespaces.queries');
            case static::MUTATION:
                return (array) config('lighthouse.namespaces.mutations');
            case static::SUBSCRIPTION:
                return (array) config('lighthouse.namespaces.subscriptions');
            default:
                return [];
        }
    }
}

<?php

namespace Nuwave\Lighthouse\Schema;

use Illuminate\Support\Str;

class RootType
{
    public const QUERY = 'Query';
    public const MUTATION = 'Mutation';
    public const SUBSCRIPTION = 'Subscription';

    public const NATIVE_TYPES = [
        'Query',
        'Mutation',
        'Subscription',
    ];

    public static function Query(): string
    {
        return self::getType('Query');
    }

    public static function Mutation(): string
    {
        return self::getType('Mutation');
    }

    public static function Subscription(): string
    {
        return self::getType('Subscription');
    }

    private static function getType(string $nativeName): string
    {
        return config('lighthouse.root_types.' . Str::lower($nativeName));
    }

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
        if (!static::isRootType($typeName)) {
            return [];
        }

        return (array) config('lighthouse.namespaces.' . Str::plural(Str::lower($typeName)));
    }
}

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
                self::QUERY,
                self::MUTATION,
                self::SUBSCRIPTION,
            ]
        );
    }
}

<?php

namespace Tests\Utils\QueriesSecondary;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Baz
{
    /**
     * The answer to life, the universe and everything.
     *
     * @var string
     */
    const THE_ANSWER = 42;

    /**
     * Return a value for the field.
     *
     * @param null                $rootValue   Usually contains the result returned from the parent field. In this case, it is always `null`.
     * @param array               $args        the arguments that were passed into the field
     * @param GraphQLContext|null $context     arbitrary data that is shared between all fields of a single query
     * @param ResolveInfo         $resolveInfo information about the query itself, such as the execution state, the field name, path to the field from the root, and more
     *
     * @return int
     */
    public function resolve($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): int
    {
        return self::THE_ANSWER;
    }
}

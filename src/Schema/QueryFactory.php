<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\QueryResolver;

class QueryFactory
{
    /**
     * Convert field definition to mutation.
     *
     * @param FieldDefinitionNode $mutation
     *
     * @return array
     */
    public static function resolve(FieldDefinitionNode $mutation)
    {
        return directives()->hasResolver($mutation)
            ? directives()->fieldResolver($mutation)
            // TODO: Create default query resolver if no directive is provided
            : QueryResolver::resolve($mutation);
    }
}

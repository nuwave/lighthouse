<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\MutationResolver;

class MutationFactory
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
            // TODO: Create default mutation resolver if no directive is provided
            : MutationResolver::resolve($mutation);
    }
}

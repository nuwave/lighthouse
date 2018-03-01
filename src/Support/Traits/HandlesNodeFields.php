<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\Factories\FieldFactory;
use Nuwave\Lighthouse\Schema\Resolvers\FieldTypeResolver;

trait HandlesNodeFields
{
    /**
     * Map collection of fields.
     *
     * @param Node $node
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getNodeFields(Node $node)
    {
        return collect($node->fields)->mapWithKeys(function (FieldDefinitionNode $field) use ($node) {
            return [$field->name->value => [
                'type' => FieldTypeResolver::resolve($field),
                'description' => trim(str_replace("\n", '', data_get($field, 'description', ''))),
                'resolve' => FieldFactory::convert($field, $node),
            ]];
        });
    }
}

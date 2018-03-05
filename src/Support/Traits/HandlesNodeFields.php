<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\Factories\FieldFactory;
use Nuwave\Lighthouse\Schema\Resolvers\FieldTypeResolver;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

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
        return collect($node->fields)
        ->mapWithKeys(function (FieldDefinitionNode $field) use ($node) {
            $value = (new FieldValue($node, $field));
            $value->setType(FieldTypeResolver::resolve($value))
                ->setDescription(trim(str_replace("\n", '', data_get($field, 'description', ''))));

            return [$field->name->value => FieldFactory::convert($value)];
        });
    }
}

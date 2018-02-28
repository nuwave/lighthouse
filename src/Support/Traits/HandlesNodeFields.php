<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Factories\FieldFactory;
use Nuwave\Lighthouse\Schema\Resolvers\FieldTypeResolver;

trait HandlesNodeFields
{
    /**
     * Map collection of fields.
     *
     * @param array $fields
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getNodeFields($fields)
    {
        return collect($fields)->mapWithKeys(function (FieldDefinitionNode $field) {
            return [$field->name->value => [
                'type' => FieldTypeResolver::resolve($field),
                'description' => trim(str_replace("\n", '', data_get($field, 'description', ''))),
                'resolve' => FieldFactory::convert($field),
            ]];
        });
    }
}

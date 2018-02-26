<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Schema\FieldFactory;
use Nuwave\Lighthouse\Schema\Resolvers\FieldTypeResolver;

class ObjectTypeResolver extends AbstractResolver
{
    /**
     * Instance of enum node to resolve.
     *
     * @var ObjectTypeDefinitionNode
     */
    protected $node;

    /**
     * Generate a GraphQL type from a node.
     *
     * @return ObjectType
     */
    public function generate()
    {
        $config = [
            'name' => $this->getName(),
            'fields' => function () {
                return $this->getFields()->toArray();
            },
        ];

        return new ObjectType($config);
    }

    /**
     * Get name for object type.
     *
     * @return string
     */
    protected function getName()
    {
        return data_get($this->node, 'name.value', '');
    }

    /**
     * Get type fields.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getFields()
    {
        return collect(data_get($this->node, 'fields', []))
            ->mapWithKeys(function (FieldDefinitionNode $field) {
                return [$field->name->value => [
                    'type' => FieldTypeResolver::resolve($field),
                    'description' => trim(str_replace("\n", '', data_get($field, 'description', ''))),
                    'resolve' => FieldFactory::convert($field),
                ]];
            });
    }
}

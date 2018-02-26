<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Type\Definition\InterfaceType;

class InterfaceResolver extends AbstractResolver
{
    /**
     * Interface node to parse.
     *
     * @var InterfaceTypeDefinitionNode
     */
    protected $node;

    /**
     * Generate a new interface type.
     *
     * @return InterfaceType
     */
    public function generate()
    {
        $config = [
            'name' => $this->getName(),
            'fields' => $this->getFields()->toArray(),
        ];

        return new InterfaceType($config);
    }

    /**
     * Get the name for the interface type.
     *
     * @return string
     */
    public function getName()
    {
        return $this->node->name->value;
    }

    /**
     * Get interface fields.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getFields()
    {
        return collect($this->node->fields)
            ->mapWithKeys(function (FieldDefinitionNode $field) {
                return [
                    $field->name->value => [
                        'type' => NodeResolver::resolve($field->type),
                        'description' => trim(str_replace("\n", '', $field->description)),
                    ],
                ];
            });
    }
}

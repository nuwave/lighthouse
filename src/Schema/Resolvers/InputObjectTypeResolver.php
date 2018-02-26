<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Definition\InputObjectType;

class InputObjectTypeResolver extends AbstractResolver
{
    /**
     * Instance of enum node to resolve.
     *
     * @var InputObjectTypeDefinitionNode
     */
    protected $node;

    /**
     * Generate a new EnumType instance.
     *
     * @return InputObjectType
     */
    public function generate()
    {
        $config = [
            'name' => $this->getName(),
            'fields' => $this->getFields(),
        ];

        return new InputObjectType($config);
    }

    /**
     * Get name for input object.
     *
     * @return string
     */
    public function getName()
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
            ->mapWithKeys(function (InputValueDefinitionNode $input) {
                return [$input->name->value => [
                    'type' => FieldTypeResolver::resolve($input),
                    'description' => trim(str_replace("\n", '', data_get($input, 'description', ''))),
                ]];
            });
    }
}

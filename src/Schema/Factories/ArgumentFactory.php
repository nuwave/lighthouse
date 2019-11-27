<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter;

class ArgumentFactory
{
    /**
     * Convert input value definitions to a executable types.
     *
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode[]|\GraphQL\Language\AST\NodeList  $definitionNodes
     * @return mixed[]
     */
    public function toTypeMap($definitionNodes): array
    {
        $arguments = [];

        foreach ($definitionNodes as $inputDefinition) {
            $arguments[$inputDefinition->name->value] = $this->convert($inputDefinition);
        }

        return $arguments;
    }

    /**
     * Convert an argument definition to an executable type.
     *
     * The returned array will be used to construct one of:
     * @see \GraphQL\Type\Definition\FieldArgument
     * @see \GraphQL\Type\Definition\InputObjectField
     *
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $definitionNode
     * @return mixed[]
     */
    public function convert(InputValueDefinitionNode $definitionNode): array
    {
        /** @var \Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter $definitionNodeConverter */
        $definitionNodeConverter = app(ExecutableTypeNodeConverter::class);
        $type = $definitionNodeConverter->convert($definitionNode->type);

        $config = [
            'name' => $definitionNode->name->value,
            'description' => data_get($definitionNode->description, 'value'),
            'type' => $type,
            'astNode' => $definitionNode,
        ];

        if ($defaultValue = $definitionNode->defaultValue) {
            $config += [
                'defaultValue' => ASTHelper::defaultValueForArgument($defaultValue, $type),
            ];
        }

        return $config;
    }
}

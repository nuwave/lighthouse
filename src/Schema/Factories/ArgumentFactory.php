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
     * @param  iterable<\GraphQL\Language\AST\InputValueDefinitionNode>  $definitionNodes
     * @return array<string, array<string, mixed>>
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
     * @return array<string, mixed> The configuration to construct an \GraphQL\Type\Definition\InputObjectField|\GraphQL\Type\Definition\FieldArgument
     */
    public function convert(InputValueDefinitionNode $definitionNode): array
    {
        /** @var \Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter $definitionNodeConverter */
        $definitionNodeConverter = app(ExecutableTypeNodeConverter::class);
        /** @var \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\InputType $type */
        $type = $definitionNodeConverter->convert($definitionNode->type);

        $config = [
            'name' => $definitionNode->name->value,
            'description' => data_get($definitionNode->description, 'value'),
            'type' => $type,
            'astNode' => $definitionNode,
        ];

        if ($defaultValue = $definitionNode->defaultValue) { // @phpstan-ignore-line TODO remove when fixed https://github.com/webonyx/graphql-php/pull/654
            $config += [
                'defaultValue' => ASTHelper::defaultValueForArgument($defaultValue, $type),
            ];
        }

        return $config;
    }
}

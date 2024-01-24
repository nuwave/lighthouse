<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\Type;
use Illuminate\Container\Container;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\ExecutableTypeNodeConverter;

/**
 * @phpstan-import-type ArgumentConfig from \GraphQL\Type\Definition\Argument
 */
class ArgumentFactory
{
    /**
     * Convert input value definitions to a executable types.
     *
     * @param  iterable<\GraphQL\Language\AST\InputValueDefinitionNode>  $definitionNodes
     *
     * @return array<string, ArgumentConfig>
     */
    public function toTypeMap(iterable $definitionNodes): array
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
     *
     * @see \GraphQL\Type\Definition\Argument
     * @see \GraphQL\Type\Definition\InputObjectField
     *
     * @return ArgumentConfig
     */
    public function convert(InputValueDefinitionNode $definitionNode): array
    {
        $definitionNodeConverter = Container::getInstance()->make(ExecutableTypeNodeConverter::class);
        $type = $definitionNodeConverter->convert($definitionNode->type);
        assert($type instanceof Type && $type instanceof InputType);

        $config = [
            'name' => $definitionNode->name->value,
            'description' => $definitionNode->description->value ?? null,
            'type' => $type,
            'astNode' => $definitionNode,
        ];

        if (($defaultValue = $definitionNode->defaultValue) !== null) {
            $config += [
                'defaultValue' => ASTHelper::defaultValueForArgument($defaultValue, $type),
            ];
        }

        return $config;
    }
}

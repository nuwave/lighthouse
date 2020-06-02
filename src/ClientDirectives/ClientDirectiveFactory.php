<?php

namespace Nuwave\Lighthouse\ClientDirectives;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter;

class ClientDirectiveFactory
{
    /**
     * @var \Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter
     */
    protected $definitionNodeConverter;

    public function __construct(ExecutableTypeNodeConverter $definitionNodeConverter)
    {
        $this->definitionNodeConverter = $definitionNodeConverter;
    }

    /**
     * Transform node to type.
     */
    public function handle(DirectiveDefinitionNode $directive): Directive
    {
        $arguments = [];
        /** @var \GraphQL\Language\AST\InputValueDefinitionNode $argument */
        foreach ($directive->arguments as $argument) {
            /** @var \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\InputType $argumentType */
            $argumentType = $this->definitionNodeConverter->convert($argument->type);

            $fieldArgumentConfig = [
                'name' => $argument->name->value,
                'description' => data_get($argument->description, 'value'),
                'type' => $argumentType,
            ];

            if ($defaultValue = $argument->defaultValue) { // @phpstan-ignore-line TODO remove when fixed https://github.com/webonyx/graphql-php/pull/654
                $fieldArgumentConfig += [
                    'defaultValue' => ASTHelper::defaultValueForArgument($defaultValue, $argumentType),
                ];
            }

            $arguments [] = new FieldArgument($fieldArgumentConfig);
        }

        $locations = [];
        // Might be a NodeList, so we can not use array_map()
        foreach ($directive->locations as $location) {
            $locations[] = $location->value;
        }

        return new Directive([
            'name' => $directive->name->value,
            'description' => data_get($directive->description, 'value'),
            'locations' => $locations,
            'args' => $arguments,
            'astNode' => $directive,
        ]);
    }
}

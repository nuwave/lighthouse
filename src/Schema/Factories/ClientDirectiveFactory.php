<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
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

    /**
     * @param  \Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter  $definitionNodeConverter
     * @return void
     */
    public function __construct(ExecutableTypeNodeConverter $definitionNodeConverter)
    {
        $this->definitionNodeConverter = $definitionNodeConverter;
    }

    /**
     * Transform node to type.
     *
     * @param  \GraphQL\Language\AST\DirectiveDefinitionNode  $directive
     * @return \GraphQL\Type\Definition\Directive
     */
    public function handle(DirectiveDefinitionNode $directive): Directive
    {
        $arguments = [];
        /** @var InputValueDefinitionNode $argument */
        foreach ($directive->arguments as $argument) {
            $argumentType = $this->definitionNodeConverter->convert($argument->type);

            $fieldArgumentConfig = [
                'name' => $argument->name->value,
                'description' => data_get($argument->description, 'value'),
                'type' => $argumentType,
            ];

            if ($defaultValue = $argument->defaultValue) {
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

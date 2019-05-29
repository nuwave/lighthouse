<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\NameNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter;

class ClientDirectiveFactory
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $definitionNodeConverter;

    /**
     * @param  \Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter  $definitionNodeConverter
     * @return void
     */
    public function __construct(DefinitionNodeConverter $definitionNodeConverter)
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
            $fieldArgumentConfig = [
                'name' => $argument->name->value,
                'description' => data_get($argument->description, 'value'),
                'type' => $this->definitionNodeConverter->toType($argument->type),
            ];

            if ($defaultValue = $argument->defaultValue) {
                $fieldArgumentConfig += [
                    'defaultValue' => $defaultValue,
                ];
            }

            $arguments [] = new FieldArgument($fieldArgumentConfig);
        }

        return new Directive([
            'name' => $directive->name->value,
            'description' => data_get($directive->description, 'value'),
            'locations' => array_map(
                function (NameNode $location): string {
                    return $location->value;
                },
                $directive->locations
            ),
            'args' => $arguments,
            'astNode' => $directive,
        ]);
    }
}

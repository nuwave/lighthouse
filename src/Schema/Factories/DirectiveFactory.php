<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\TypeNodeConverter;

class DirectiveFactory
{
    /**
     * @var \Nuwave\Lighthouse\Schema\AST\TypeNodeConverter
     */
    protected $typeNodeConverter;

    public function __construct(TypeNodeConverter $typeNodeConverter)
    {
        $this->typeNodeConverter = $typeNodeConverter;
    }

    /**
     * Transform node to type.
     */
    public function handle(DirectiveDefinitionNode $directive): Directive
    {
        $arguments = [];
        foreach ($directive->arguments as $argument) {
            /** @var \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\InputType $argumentType */
            $argumentType = $this->typeNodeConverter->convert($argument->type);

            $fieldArgumentConfig = [
                'name' => $argument->name->value,
                'description' => $argument->description->value ?? null,
                'type' => $argumentType,
            ];

            if ($defaultValue = $argument->defaultValue) {
                $fieldArgumentConfig += [
                    'defaultValue' => ASTHelper::defaultValueForArgument($defaultValue, $argumentType),
                ];
            }

            $arguments[] = new FieldArgument($fieldArgumentConfig);
        }

        $locations = [];
        // Might be a NodeList, so we can not use array_map()
        foreach ($directive->locations as $location) {
            $locations[] = $location->value;
        }

        return new Directive([
            'name' => $directive->name->value,
            'description' => $directive->description->value ?? null,
            'locations' => $locations,
            'args' => $arguments,
            'isRepeatable' => $directive->repeatable,
            'astNode' => $directive,
        ]);
    }
}

<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\TypeNodeConverter;

class DirectiveFactory
{
    public function __construct(
        protected TypeNodeConverter $typeNodeConverter,
    ) {}

    /** Transform node to type. */
    public function handle(DirectiveDefinitionNode $directive): Directive
    {
        $arguments = [];
        foreach ($directive->arguments as $argument) {
            $argumentType = $this->typeNodeConverter->convert($argument->type);
            assert($argumentType instanceof Type && $argumentType instanceof InputType);

            $argumentConfig = [
                'name' => $argument->name->value,
                'description' => $argument->description->value ?? null,
                'type' => $argumentType,
            ];

            if (($defaultValue = $argument->defaultValue) !== null) {
                $argumentConfig += [
                    'defaultValue' => ASTHelper::defaultValueForArgument($defaultValue, $argumentType),
                ];
            }

            $arguments[] = $argumentConfig;
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

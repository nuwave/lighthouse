<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Extensions\ArgumentExtensions;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedAfter;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedBefore;

class ArgumentFactory
{
    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    /**
     * ArgumentFactory constructor.
     * @param \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory $directiveFactory
     */
    public function __construct(DirectiveFactory $directiveFactory)
    {
        $this->directiveFactory = $directiveFactory;
    }

    /**
     * Convert argument definition to type.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\ArgumentValue  $argumentValue
     * @return array
     */
    public function handle(ArgumentValue $argumentValue): array
    {
        $definition = $argumentValue->getAstNode();

        $argumentType = $argumentValue->getType();

        $fieldArgument = [
            'name' => $argumentValue->getName(),
            'description' => data_get($definition->description, 'value'),
            'type' => $argumentType,
            'astNode' => $definition,
        ];

        if ($defaultValue = $definition->defaultValue) {
            $fieldArgument += [
                'defaultValue' => ASTHelper::defaultValueForArgument($defaultValue, $argumentType),
            ];
        }

        $extensions = new ArgumentExtensions();
        $extensions->resolveBefore = $this
            ->directiveFactory
            ->createAssociatedDirectivesOfType($definition, ResolveNestedBefore::class);
        $extensions->resolveAfter = $this
            ->directiveFactory
            ->createAssociatedDirectivesOfType($definition, ResolveNestedAfter::class);
        $fieldArgument['lighthouse'] = $extensions;

        // Add any dynamically declared public properties of the FieldArgument
        $fieldArgument += get_object_vars($argumentValue);

        // Used to construct a FieldArgument class
        return $fieldArgument;
    }
}

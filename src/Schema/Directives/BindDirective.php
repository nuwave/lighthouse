<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\InputFieldManipulator;

use function class_exists;
use function is_callable;
use function is_subclass_of;
use function sprintf;

class BindDirective extends BaseDirective implements
    ArgTransformerDirective,
    ArgDirective,
    ArgDirectiveForArray,
    ArgManipulator,
    InputFieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Automatically inject (model) instances directly into a resolver argument or input field. For example, instead 
of injecting a user's ID, you can inject the entire User model instance that matches the given ID.

This is a GraphQL analogue for Laravel's Route Binding.
"""
directive @bind(
    """
    Specify the class name of the binding to use. This can be either an Eloquent 
    model or callable class to bind any other instance than a model.
    """
    class: String!
    
    """
    Specify the column name to use when binding Eloquent models.
    By default, "id" is used the the primary key column.
    """
    column: String! = "id"
    
    """
    Specify the relations to eager-load when binding Eloquent models.
    """
    with: [String!]! = []
    
    """
    Specify whether the binding should be considered optional. When optional, the argument's 
    value is set to `null` when no matching binding could be resolved. When the binding 
    isn't optional, an exception is thrown and the field resolver will not be invoked. 
    """
    optional: Boolean! = false
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $this->validateClassArg([
            'argument' => $argDefinition->name->value,
            'field' => $parentField->name->value,
        ]);
    }

    public function manipulateInputFieldDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$inputField,
        InputObjectTypeDefinitionNode &$parentInput,
    ): void {
        $this->validateClassArg([
            'field' => $inputField->name->value,
            'input' => $parentInput->name->value,
        ]);
    }

    /**
     * @param array<string, string> $exceptionMessageArgs
     */
    private function validateClassArg(array $exceptionMessageArgs): void
    {
        $directive = $this->name();
        $class = $this->directiveArgValue('class');

        if (! class_exists($class)) {
            throw new DefinitionException(sprintf(
                "@$directive argument `class` defined on %s `%s` of %s `%s` " .
                "must be an existing class, received `$class`.",
                ...$this->spreadKeysAndValues($exceptionMessageArgs),
            ));
        }

        if (is_subclass_of($class, Model::class)) {
            return;
        }

        if (is_callable($class)) {
            return;
        }

        throw new DefinitionException(sprintf(
            "@$directive argument `class` defined on %s `%s` of %s `%s` must be " .
            "an Eloquent model or a callable class, received `$class`.",
            ...$this->spreadKeysAndValues($exceptionMessageArgs),
        ));
    }

    /**
     * @param array<string, string> $keyedValues
     * @return array<int, string>
     */
    private function spreadKeysAndValues(array $keyedValues): array
    {
        return Collection::make($keyedValues)->reduce(fn (array $carry, string $value, string $key): array => [
            ...$carry, $key, $value,
        ], []);
    }

    public function transform(mixed $argumentValue): mixed
    {
        return $argumentValue;
    }
}

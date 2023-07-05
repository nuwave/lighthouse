<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Utils;

class SpreadDirective extends BaseDirective implements FieldMiddleware, ArgManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Merge the fields of a nested input object into the arguments of its parent
when processing the field arguments given by a client.
"""
directive @spread on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->addArgumentSetTransformer(fn (ArgumentSet $argumentSet): ArgumentSet => $this->spread($argumentSet));
    }

    /** Apply the @spread directive and return a new, modified ArgumentSet. */
    protected function spread(ArgumentSet $original): ArgumentSet
    {
        $next = new ArgumentSet();
        $next->directives = $original->directives;
        $next->undefined = $original->undefined;

        foreach ($original->arguments as $name => $argument) {
            // Recurse down first, as that resolves the more deeply nested spreads first
            $argument->value = Utils::mapEach(
                function ($value) {
                    if ($value instanceof ArgumentSet) {
                        return $this->spread($value);
                    }

                    return $value;
                },
                $argument->value,
            );

            if (
                $argument->value instanceof ArgumentSet
                && $argument->directives->contains(
                    Utils::instanceofMatcher(static::class),
                )
            ) {
                $next->arguments += $argument->value->arguments;
            } else {
                $next->arguments[$name] = $argument;
            }
        }

        return $next;
    }

    public function manipulateArgDefinition(DocumentAST &$documentAST, InputValueDefinitionNode &$argDefinition, FieldDefinitionNode &$parentField, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        $type = $argDefinition->type instanceof NonNullTypeNode
            ? $argDefinition->type->type
            : $argDefinition->type;

        if ($type instanceof ListTypeNode) {
            throw new DefinitionException("Cannot use @spread on argument {$parentType->name->value}.{$parentField->name->value}:{$argDefinition->name->value} with a list type.");
        }
    }
}

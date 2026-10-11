<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Printer;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\InputFieldManipulator;

/**
 * Marker for nested input grouping — resolution is handled by ResolveNested.
 */
class NestDirective extends BaseDirective implements ArgResolver, ArgManipulator, InputFieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
A no-op nested arg resolver that delegates all calls
to the ArgResolver directives attached to the children.
"""
directive @nest on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /** Handled by ResolveNested — direct invocation is not supported. */
    public function __invoke(mixed $root, mixed $value): void
    {
        throw new \LogicException('NestDirective must not be invoked directly, use ResolveNested.');
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $this->ensureInputObjectType(
            $argDefinition,
            $documentAST,
            "{$parentType->name->value}.{$parentField->name->value}:{$argDefinition->name->value}",
        );
    }

    public function manipulateInputFieldDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$inputField,
        InputObjectTypeDefinitionNode &$parentInput,
    ): void {
        $this->ensureInputObjectType(
            $inputField,
            $documentAST,
            "{$parentInput->name->value}.{$inputField->name->value}",
        );
    }

    protected function ensureInputObjectType(InputValueDefinitionNode $definition, DocumentAST $documentAST, string $location): void
    {
        $type = $definition->type instanceof NonNullTypeNode
            ? $definition->type->type
            : $definition->type;

        if ($type instanceof ListTypeNode) {
            $printedType = Printer::doPrint($definition->type);

            throw new DefinitionException("The @nest directive must be used on input object types, got {$printedType} on {$location}.");
        }

        $typeName = ASTHelper::getUnderlyingTypeName($definition);
        $typeDefinition = $documentAST->types[$typeName] ?? null;

        if (! $typeDefinition instanceof InputObjectTypeDefinitionNode) {
            $printedType = Printer::doPrint($definition->type);

            throw new DefinitionException("The @nest directive must be used on input object types, got {$printedType} on {$location}.");
        }
    }
}

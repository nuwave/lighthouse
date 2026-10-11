<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Void;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class VoidDirective extends BaseDirective implements FieldManipulator, FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Mark a field that returns no value.

The return type of the field will be changed to `Void!`, defined as `enum Void { VOID }`.
Whatever result is returned from the resolver will be replaced with `VOID`.
"""
directive @void on FIELD_DEFINITION
GRAPHQL;
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        $fieldDefinition->type = Parser::typeReference(/** @lang GraphQL */ 'Void!');
    }

    public function handleField(FieldValue $fieldValue): void
    {
        // Just ignore whatever the original resolver is doing and always return a singular value
        $fieldValue->resultHandler(static fn (): string => VoidServiceProvider::VOID);
    }
}

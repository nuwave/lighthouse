<?php

namespace Nuwave\Lighthouse\SoftDeletes;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class SoftDeletesDirective extends BaseDirective implements FieldManipulator, DefinedDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'softDeletes';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Allows to filter if trashed elements should be fetched.
This manipulates the schema by adding the argument
`trashed: Trashed @trashed` to the field.
"""
directive @softDeletes on FIELD_DEFINITION
SDL;
    }

    /**
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @return void
     */
    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode &$parentType): void
    {
        $softDeletesArgument = PartialParser::inputValueDefinition(/* @lang GraphQL */ <<<'SDL'
"""
Allows to filter if trashed elements should be fetched.
"""
trashed: Trashed @trashed
SDL
        );
        $fieldDefinition->arguments = ASTHelper::mergeNodeList($fieldDefinition->arguments, [$softDeletesArgument]);
    }
}

<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
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
        $softDeletesArgument = PartialParser::inputValueDefinition("\"Define if soft deleted models should be also fetched.\"\ntrashed: Trash @trash");
        $fieldDefinition->arguments = ASTHelper::mergeNodeList($fieldDefinition->arguments, [$softDeletesArgument]);
    }
}

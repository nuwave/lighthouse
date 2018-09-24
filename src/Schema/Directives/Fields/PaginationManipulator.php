<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Types\PaginatorField;
use Nuwave\Lighthouse\Schema\Types\ConnectionField;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

abstract class PaginationManipulator extends BaseDirective
{
    const PAGINATION_TYPE_PAGINATOR = 'paginator';
    const PAGINATION_TYPE_CONNECTION = 'connection';
    const PAGINATION_ALIAS_RELAY = 'relay';

    /**
     * @param string $paginationType
     *
     * @return bool
     */
    protected function isValidPaginationType($paginationType)
    {
        return self::PAGINATION_TYPE_PAGINATOR === $paginationType ||
        self::PAGINATION_TYPE_CONNECTION === $paginationType;
    }

    /**
     * @param string $paginationType
     *
     * @return string
     */
    protected function convertAliasToPaginationType($paginationType)
    {
        if (self::PAGINATION_ALIAS_RELAY === $paginationType) {
            return self::PAGINATION_TYPE_CONNECTION;
        }

        return $paginationType;
    }

    /**
     * Register connection w/ schema.
     *
     * @param FieldDefinitionNode      $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST              $documentAST
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    protected function registerConnection(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $documentAST): DocumentAST
    {
        $fieldTypeName = ASTHelper::getFieldTypeName($fieldDefinition);
        $connectionTypeName = "{$fieldTypeName}Connection";
        $connectionEdgeName = "{$fieldTypeName}Edge";
        $connectionFieldName = addslashes(ConnectionField::class);

        $connectionType = PartialParser::objectTypeDefinition("
            type $connectionTypeName {
                pageInfo: PageInfo! @field(class: \"$connectionFieldName\" method: \"pageInfoResolver\")
                edges: [$connectionEdgeName] @field(class: \"$connectionFieldName\" method: \"edgeResolver\")
            }
        ");

        $connectionEdge = PartialParser::objectTypeDefinition("
            type $connectionEdgeName {
                node: $fieldTypeName
                cursor: String!
            }
        ");

        $connectionArguments = PartialParser::inputValueDefinitions([
            'first: Int!',
            'after: String',
        ]);

        $fieldDefinition->arguments = ASTHelper::mergeNodeList($fieldDefinition->arguments, $connectionArguments);
        $fieldDefinition->type = PartialParser::namedType($connectionTypeName);
        $parentType->fields = ASTHelper::mergeNodeList($parentType->fields, [$fieldDefinition]);

        $documentAST->setDefinition($connectionType);
        $documentAST->setDefinition($connectionEdge);
        $documentAST->setDefinition($parentType);

        return $documentAST;
    }

    /**
     * Register paginator w/ schema.
     *
     * @param FieldDefinitionNode      $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST              $documentAST
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    protected function registerPaginator(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $documentAST): DocumentAST
    {
        $fieldTypeName = ASTHelper::getFieldTypeName($fieldDefinition);
        $paginatorTypeName = "{$fieldTypeName}Paginator";
        $paginatorFieldClassName = addslashes(PaginatorField::class);

        $paginatorType = PartialParser::objectTypeDefinition("
            type $paginatorTypeName {
                paginatorInfo: PaginatorInfo! @field(class: \"$paginatorFieldClassName\" method: \"paginatorInfoResolver\")
                data: [$fieldTypeName!]! @field(class: \"$paginatorFieldClassName\" method: \"dataResolver\")
            }
        ");

        $paginationArguments = PartialParser::inputValueDefinitions([
            'count: Int!',
            'page: Int',
        ]);

        $fieldDefinition->arguments = ASTHelper::mergeNodeList($fieldDefinition->arguments, $paginationArguments);
        $fieldDefinition->type = PartialParser::namedType($paginatorTypeName);
        $parentType->fields = ASTHelper::mergeNodeList($parentType->fields, [$fieldDefinition]);

        $documentAST->setDefinition($paginatorType);
        $documentAST->setDefinition($parentType);

        return $documentAST;
    }
}

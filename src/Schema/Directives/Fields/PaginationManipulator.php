<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Exceptions\ParseException;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Types\PaginatorField;
use Nuwave\Lighthouse\Schema\Types\ConnectionField;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class PaginationManipulator
{
    // The default is offset-based pagination
    const PAGINATION_TYPE_PAGINATOR = 'paginator';
    const PAGINATION_ALIAS_DEFAULT = 'default';
    
    // Those are both aliases for a Connection style pagination
    const PAGINATION_TYPE_CONNECTION = 'connection';
    const PAGINATION_ALIAS_RELAY = 'relay';
    
    /**
     * Apply possible aliases and throw if the given pagination type is invalid.
     *
     * @param string $paginationType
     *
     * @throws DirectiveException
     *
     * @return string
     */
    public static function assertValidPaginationType(string $paginationType): string
    {
        if (self::PAGINATION_ALIAS_RELAY === $paginationType) {
            return self::PAGINATION_TYPE_CONNECTION;
        }
        
        if (self::PAGINATION_ALIAS_DEFAULT === $paginationType) {
            return self::PAGINATION_TYPE_PAGINATOR;
        }
        
        if(in_array($paginationType, [
            self::PAGINATION_TYPE_PAGINATOR,
            self::PAGINATION_TYPE_CONNECTION
        ])){
            return $paginationType;
        }
        
        throw new DirectiveException("Found invalid pagination type: {$paginationType}");
    }

    /**
     * Transform the definition for a field to a field with pagination.
     *
     * This makes either an offset-based Paginator or a cursor-based Connection.
     * The types in between are automatically generated and applied to the schema.
     *
     * @param string $paginationType
     * @param FieldDefinitionNode $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST $current
     *
     * @throws DefinitionException
     * @throws DirectiveException
     * @throws ParseException
     *
     * @return DocumentAST
     */
    public static function transformToPaginatedField(string $paginationType, FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current): DocumentAST
    {
        switch (self::assertValidPaginationType($paginationType)) {
            case PaginationManipulator::PAGINATION_TYPE_CONNECTION:
                return PaginationManipulator::registerConnection($fieldDefinition, $parentType, $current);
            case PaginationManipulator::PAGINATION_TYPE_PAGINATOR:
            default:
                return PaginationManipulator::registerPaginator($fieldDefinition, $parentType, $current);
        }
    }

    /**
     * Register connection w/ schema.
     *
     * @param FieldDefinitionNode $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST $documentAST
     *
     * @throws DefinitionException
     * @throws ParseException
     *
     * @return DocumentAST
     */
    public static function registerConnection(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $documentAST): DocumentAST
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
     * @param FieldDefinitionNode $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST $documentAST
     *
     * @throws DefinitionException
     * @throws ParseException
     *
     * @return DocumentAST
     */
    public static function registerPaginator(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $documentAST): DocumentAST
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

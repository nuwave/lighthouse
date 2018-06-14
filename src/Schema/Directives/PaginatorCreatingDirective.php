<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\Fields\AbstractFieldDirective;
use Nuwave\Lighthouse\Schema\Types\ConnectionField;
use Nuwave\Lighthouse\Schema\Types\PaginatorField;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

abstract class PaginatorCreatingDirective extends AbstractFieldDirective
{
    use HandlesDirectives;

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
     * @param DocumentAST              $current
     * @param DocumentAST              $original
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    protected function registerConnection(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current, DocumentAST $original)
    {
        $connectionTypeName = $this->connectionTypeName($fieldDefinition, $parentType);
        $connectionEdgeName = $this->connectionEdgeName($fieldDefinition, $parentType);
        $connectionFieldName = addslashes(ConnectionField::class);

        $connectionType = PartialParser::objectTypeDefinition("
            type $connectionTypeName {
                pageInfo: PageInfo! @field(class: \"$connectionFieldName\" method: \"pageInfoResolver\")
                edges: [$connectionEdgeName] @field(class: \"$connectionFieldName\" method: \"edgeResolver\")
            }
        ");
        $current->setDefinition($connectionType);

        $nodeName = $this->unpackNodeToString($fieldDefinition);
        $connectionEdge = PartialParser::objectTypeDefinition("
            type $connectionEdgeName {
                node: $nodeName
                cursor: String!
            }
        ");
        $current->setDefinition($connectionEdge);

        $connectionArguments = PartialParser::inputValueDefinitions([
            'first: Int!',
            'after: String',
        ]);
        $fieldDefinition->arguments = ASTHelper::mergeNodeList($fieldDefinition->arguments, $connectionArguments);

        $fieldDefinition->type = Parser::parseType($connectionTypeName);

        $parentType->fields = ASTHelper::mergeNodeList($parentType->fields, [$fieldDefinition]);
        $current->setDefinition($parentType);

        return $current;
    }

    /**
     * Register paginator w/ schema.
     *
     * @param FieldDefinitionNode      $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST              $current
     * @param DocumentAST              $original
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    protected function registerPaginator(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current, DocumentAST $original)
    {
        $paginatorTypeName = $this->paginatorTypeName($fieldDefinition, $parentType);
        $paginatorFieldClassName = addslashes(PaginatorField::class);
        $fieldTypeName = $this->unpackNodeToString($fieldDefinition);

        $paginatorType = PartialParser::objectTypeDefinition("
            type $paginatorTypeName {
                paginatorInfo: PaginatorInfo! @field(class: \"$paginatorFieldClassName\" method: \"paginatorInfoResolver\")
                data: [$fieldTypeName!]! @field(class: \"$paginatorFieldClassName\" method: \"dataResolver\")
            }
        ");
        $current->setDefinition($paginatorType);

        $paginationArguments = PartialParser::inputValueDefinitions([
            'count: Int!',
            'page: Int',
        ]);
        $fieldDefinition->arguments = ASTHelper::mergeNodeList($fieldDefinition->arguments, $paginationArguments);

        $fieldDefinition->type = Parser::parseType($paginatorTypeName);

        $parentType->fields = ASTHelper::mergeNodeList($parentType->fields, [$fieldDefinition]);
        $current->setDefinition($parentType);

        return $current;
    }

    /**
     * Get paginator type name.
     *
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return string
     */
    protected function paginatorTypeName(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parent)
    {
        return studly_case(
            $this->parentTypeName($parent)
            .$this->singularFieldName($fieldDefinition)
            .'_Paginator');
    }

    /**
     * Get connection type name.
     *
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return string
     */
    protected function connectionTypeName(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parent)
    {
        return studly_case(
            $this->parentTypeName($parent)
            .$this->singularFieldName($fieldDefinition)
            .'_Connection');
    }

    /**
     * Get connection edge name.
     *
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return string
     */
    protected function connectionEdgeName(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parent)
    {
        return studly_case(
            $this->parentTypeName($parent)
            .$this->singularFieldName($fieldDefinition)
            .'_Edge');
    }

    /**
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return string
     */
    protected function singularFieldName(FieldDefinitionNode $fieldDefinition)
    {
        return str_singular($fieldDefinition->name->value);
    }

    /**
     * @param ObjectTypeDefinitionNode $objectType
     *
     * @return string
     */
    protected function parentTypeName(ObjectTypeDefinitionNode $objectType)
    {
        $name = $objectType->name->value;

        return 'Query' === $name ? '' : $name.'_';
    }
}

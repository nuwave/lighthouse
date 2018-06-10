<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Types\ConnectionField;
use Nuwave\Lighthouse\Schema\Types\PaginatorField;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

trait CreatesPaginators
{
    // TODO: Ugh, get rid of this...
    use HandlesDirectives;

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
    public function registerConnection(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current, DocumentAST $original)
    {
        $connectionTypeName = $this->connectionTypeName($fieldDefinition, $parentType);
        $connectionEdgeName = $this->connectionEdgeName($fieldDefinition, $parentType);
        $connectionFieldName = addslashes(ConnectionField::class);

        $connectionType = PartialParser::objectType("
            type $connectionTypeName {
                pageInfo: PageInfo! @field(class: \"$connectionFieldName\" method: \"pageInfoResolver\")
                edges: [$connectionEdgeName] @field(class: \"$connectionFieldName\" method: \"edgeResolver\")
            }
        ");
        $current->setDefinition($connectionType);

        $nodeName = $this->unpackNodeToString($fieldDefinition);
        $connectionEdge = PartialParser::objectType("
            type $connectionEdgeName {
                node: $nodeName
                cursor: String!
            }
        ");
        $current->setDefinition($connectionEdge);

        $connectionArguments = PartialParser::arguments([
            'first: Int!',
            'after: String'
        ]);
        $fieldDefinition->arguments = array_merge($fieldDefinition->arguments, $connectionArguments);
        
        $fieldDefinition->type = Parser::parseType($connectionTypeName);
        
        $parentType->fields->merge([$fieldDefinition]);
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
    public function registerPaginator(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current, DocumentAST $original)
    {
        $paginatorTypeName = $this->paginatorTypeName($fieldDefinition, $parentType);
        $paginatorFieldClassName = addslashes(PaginatorField::class);
        $fieldTypeName = $this->unpackNodeToString($fieldDefinition);
        
        $paginatorType = PartialParser::objectType("
            type $paginatorTypeName {
                paginatorInfo: PaginatorInfo! @field(class: \"$paginatorFieldClassName\" method: \"paginatorInfoResolver\")
                data: [$fieldTypeName!]! @field(class: \"$paginatorFieldClassName\" method: \"dataResolver\")
            }
        ");
        $current->setDefinition($paginatorType);

        $paginationArguments = PartialParser::arguments([
            'count: Int!',
            'page: Int'
        ]);
        $fieldDefinition->arguments = array_merge($fieldDefinition->arguments, $paginationArguments);
    
        $fieldDefinition->type = Parser::parseType($paginatorTypeName);
    
        $parentType->fields->merge([$fieldDefinition]);
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

    protected function parentTypeName(ObjectTypeDefinitionNode $objectType)
    {
        $name = $objectType->name->value;

        return 'Query' === $name ? '' : $name.'_';
    }
}

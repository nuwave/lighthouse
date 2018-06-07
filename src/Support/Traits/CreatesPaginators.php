<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Schema\Types\ConnectionField;
use Nuwave\Lighthouse\Schema\Types\PaginatorField;
use Nuwave\Lighthouse\Schema\Utils\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

trait CreatesPaginators
{
    // TODO: Ugh, get rid of this...
    use HandlesDirectives;

    /**
     * Register connection w/ schema.
     *
     * @param FieldDefinitionNode      $fieldDefinition
     * @param DocumentAST              $current
     * @param DocumentAST              $original
     * @param ObjectTypeDefinitionNode $parentType
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

        $connectionDefinition = DocumentAST::parseObjectType("
            type $connectionTypeName {
                pageInfo: PageInfo! @field(class: \"$connectionFieldName\" method: \"pageInfoResolver\")
                edges: [$connectionEdgeName] @field(class: \"$connectionFieldName\" method: \"edgeResolver\")
            }
        ");
        $current->setObjectType($connectionDefinition);

        $nodeName = $this->unpackNodeToString($fieldDefinition);
        $current->setObjectTypeFromString("
            type $connectionEdgeName {
                node: $nodeName
                cursor: String!
            }
        ");

        $fieldDefinition->arguments = DocumentAST::parseArgumentDefinitions('first: Int! after: String')->merge($fieldDefinition->arguments);
        $fieldDefinition->type = Parser::parseType($connectionTypeName);
        $current->setObjectType(DocumentAST::addFieldToObjectType($parentType, $fieldDefinition));

        return $current;
    }

    /**
     * Register paginator w/ schema.
     *
     * @param FieldDefinitionNode      $fieldDefinition
     * @param DocumentAST              $current
     * @param DocumentAST              $original
     * @param ObjectTypeDefinitionNode $objectType
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

        $paginatorDefinition = DocumentAST::parseObjectType("
            type $paginatorTypeName {
                paginatorInfo: PaginatorInfo! @field(class: \"$paginatorFieldClassName\" method: \"paginatorInfoResolver\")
                data: [$fieldTypeName!]! @field(class: \"$paginatorFieldClassName\" method: \"dataResolver\")
            }        
        ");
        $current->setObjectType($paginatorDefinition);

        $fieldDefinition->arguments = DocumentAST::parseArgumentDefinitions('count: Int! page: Int')->merge($fieldDefinition->arguments);
        $fieldDefinition->type = Parser::parseType($paginatorTypeName);
        $current->setObjectType(DocumentAST::addFieldToObjectType($parentType, $fieldDefinition));

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

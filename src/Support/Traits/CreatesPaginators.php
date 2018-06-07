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
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\NodeValue;


trait CreatesPaginators
{
    // TODO: Ugh, get rid of this...
    use HandlesDirectives;

    /**
     * Register connection w/ schema.
     *
     * @param FieldDefinitionNode $fieldDefinition
     * @throws \Exception
     */
    protected function registerConnection(FieldDefinitionNode $fieldDefinition, DocumentAST $current, DocumentAST $original)
    {
        $connectionTypeName = $this->connectionTypeName($fieldDefinition);
        $connectionEdgeName = $this->connectionEdgeName($fieldDefinition);

        $connectionFieldName = addslashes(ConnectionField::class);
        $connectionDefinitionString = sprintf('
            type %s { pageInfo: PageInfo! @field(class: "%s" method: "pageInfoResolver") edges: [%s] @field(class: "%s" method: "edgeResolver") }',
            $connectionTypeName,
            $connectionFieldName,
            $connectionEdgeName,
            $connectionFieldName
        );
        $connectionDefinition = DocumentAST::parseSingleDefinition($connectionDefinitionString);
        $current->setDefinition($connectionDefinition);

        $nodeName = $this->unpackNodeToString($fieldDefinition);
        $current->setDefinitionFromString("type $connectionEdgeName { node: $nodeName cursor: String! }");

        $field = DocumentAST::parseFieldDefinition('users(first: Int! after: String): UserConnection');
//        $fieldDefinition->arguments = DocumentAST::parseArgumentDefinitions('first: Int! after: String')->merge($fieldDefinition->arguments);
//        $fieldDefinition->type = $connectionDefinition;
//        dd($fieldDefinition);
        // todo generalize this to all parent types
        $current->addFieldToQueryType($field);

        return $current;
//
//        DocumentAST::parse($schema)->definitions()
//            ->map(function ($node) {
//                return $this->convertNode($node);
//            })
//            ->filter()
//            ->each(function ($type) use ($fieldDefinition) {
//                schema()->type($type);
//
//                if (ends_with($type->name, 'Connection')) {
//                    $fieldDefinition->setType($type);
//                }
//            });
    }

    /**
     * Convert node to type.
     *
     * @param Node $node
     *
     * @return \GraphQL\Type\Definition\Type
     * @throws \Exception
     */
    protected function convertNode(Node $node)
    {
        /** @var NodeFactory $nodeFactory */
        $nodeFactory = app(NodeFactory::class);
        return $nodeFactory->handle(new NodeValue($node));
    }

    /**
     * Register paginator w/ schema.
     *
     * @param FieldValue $value
     */
    protected function registerPaginator(FieldValue $value)
    {
        $schema = sprintf(
            'type Paginator { paginator(count: Int! page: Int): String }
            type %s { paginatorInfo: PaginatorInfo! @field(class: "%s" method: "%s") data: [%s!]! @field(class: "%s" method: "%s") }',
            $this->paginatorTypeName($value),
            addslashes(PaginatorField::class),
            'paginatorInfoResolver',
            $this->unpackNodeToString($value->getField()),
            addslashes(PaginatorField::class),
            'dataResolver'
        );

        DocumentAST::parse($schema)->definitions()
            ->map(function ($node) use ($value) {
                if ('Paginator' === $node->name->value) {
                    $paginatorField = data_get($node, 'fields.0');
                    $field = $value->getField();
                    $field->arguments = $paginatorField->arguments->merge($field->arguments);

                    return null;
                }

                return $this->convertNode($node);
            })
            ->filter()
            ->each(function ($type) use ($value) {
                schema()->type($type);

                if (ends_with($type->name, 'Paginator')) {
                    $value->setType($type);
                }
            });
    }

    /**
     * Get paginator type name.
     *
     * @param FieldValue $value
     *
     * @return string
     */
    protected function paginatorTypeName(FieldValue $value)
    {
        $parent = $value->getNodeName();
        $child = str_singular($value->getField()->name->value);

        return studly_case($parent.'_'.$child.'_Paginator');
    }

    /**
     * Get connection type name.
     *
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return string
     */
    protected function connectionTypeName(FieldDefinitionNode $fieldDefinition)
    {
        $fieldName = str_singular($fieldDefinition->name->value);

        return studly_case($fieldName.'_Connection');
    }

    /**
     * Get connection edge name.
     *
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return string
     */
    protected function connectionEdgeName(FieldDefinitionNode $fieldDefinition)
    {
        $fieldName = str_singular($fieldDefinition->name->value);

        return studly_case($fieldName.'_Edge');
    }
}

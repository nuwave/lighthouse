<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Traits\CanParseTypes;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class GenerateDirective implements Directive
{
    use HandlesDirectives, CanParseTypes;
    
    /**
     * @param ObjectTypeDefinitionNode $definitionNode
     * @param DocumentNode $documentNode
     * @param DocumentNode $originalDocument May be required to infer relationship information
     *
     * @return DocumentNode
     */
    public function generate(
        ObjectTypeDefinitionNode $definitionNode,
        DocumentNode $documentNode,
        DocumentNode $originalDocument
    ) {
        $directive = $this->nodeDirective($definitionNode, $this->name());
        $nodeName = $definitionNode->name->value;

        // Get config values and set defaults
        $model = $this->directiveArgValue($directive, 'model');
        $crud = $this->directiveArgValue($directive, 'crud', false);
        $read = $this->directiveArgValue($directive, 'read', false);

        if ($crud || $read) {
            $query = $this->getRootQueryDefinition($documentNode);

            $query = $this->addPaginatedQuery($query, $nodeName, $model);
            $query = $this->addQueryById($query, $nodeName, $model);

            $documentNode = $this->setRootQueryDefinition($documentNode, $query);
        }
        
        return $documentNode;
    }
    
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'generate';
    }
    
    /**
     * @param DocumentNode $documentNode
     *
     * @return ObjectTypeDefinitionNode
     */
    protected function getRootQueryDefinition(DocumentNode $documentNode)
    {
        return collect($documentNode->definitions)->first(function ($node) {
            return $node->name->value === 'Query';
        }) ?: $this->getRootQueryDefinition($this->parseSchema('type Query{}'));
    }
    

    protected function addPaginatedQuery($query, $nodeName, $model)
    {
        $pluralName = lcfirst(str_plural($nodeName));
        $queryMultiple = $this->parseFieldDefinition($pluralName . ': [' . $nodeName . '!]! @paginate(model: "' . $model . '")');
        return $this->addFieldToRootType($queryMultiple, $query);
    }
    
    protected function addFieldToRootType(FieldDefinitionNode $field, ObjectTypeDefinitionNode $root)
    {
        // webonyx/graphql-php is inconsistent here
        // This should be FieldDefinitionNode[] but comes back as NodeList
        /** @var NodeList $nodeList */
        $nodeList = $root->fields;

        $root->fields = $nodeList->merge([$field]);

        return $root;
    }

    protected function addQueryById($query, $nodeName, $model)
    {
        $singularName = lcfirst(str_singular($nodeName));
        $queryById = $this->parseFieldDefinition($singularName . '(id: ID!): ' . $nodeName . '@find(model: "' . $model . '")');

        return $this->addFieldToRootType($queryById, $query);
    }

    /**
     * @param DocumentNode $documentNode
     * @param ObjectTypeDefinitionNode $query
     *
     * @return DocumentNode
     */
    protected function setRootQueryDefinition(DocumentNode $documentNode, ObjectTypeDefinitionNode $query)
    {
        $documentNode->definitions = collect($documentNode->definitions)->reject(function ($type) {
            return $type->name === 'Query';
        })->push($query)->toArray();

        return $documentNode;
    }
}

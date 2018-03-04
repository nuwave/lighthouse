<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\Factories\MutationFactory;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Schema\Factories\QueryFactory;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

trait CanParseTypes
{
    /**
     * Parse schema to definitions.
     *
     * @param string $schema
     *
     * @return DocumentNode
     */
    public function parseSchema($schema)
    {
        return Parser::parse($schema);
    }

    /**
     * Set enum types.
     *
     * @param DocumentNode $document
     *
     * @return array
     */
    public function getEnums(DocumentNode $document)
    {
        return $this->definitions($document)->filter(function ($def) {
            return $def instanceof EnumTypeDefinitionNode;
        })->map(function (EnumTypeDefinitionNode $enum) {
            return NodeFactory::enum(NodeValue::init($enum));
        })->toArray();
    }

    /**
     * Set interface types.
     *
     * @param DocumentNode $document
     *
     * @return array
     */
    public function getInterfaces(DocumentNode $document)
    {
        return $this->definitions($document)->filter(function ($def) {
            return $def instanceof InterfaceTypeDefinitionNode;
        })->map(function (InterfaceTypeDefinitionNode $interface) {
            return NodeFactory::interface(NodeValue::init($interface));
        })->toArray();
    }

    /**
     * Set scalar types.
     *
     * @param DocumentNode $document
     *
     * @return array
     */
    public function getScalars(DocumentNode $document)
    {
        return $this->definitions($document)->filter(function ($def) {
            return $def instanceof ScalarTypeDefinitionNode;
        })->map(function (ScalarTypeDefinitionNode $scalar) {
            return NodeFactory::scalar(NodeValue::init($scalar));
        })->toArray();
    }

    /**
     * Set object types.
     *
     * @param DocumentNode $document
     *
     * @return array
     */
    public function getObjectTypes(DocumentNode $document)
    {
        return $this->objectTypes($document)
        ->filter(function (ObjectTypeDefinitionNode $objectType) {
            return ! in_array($objectType->name->value, ['Mutation', 'Query']);
        })->map(function (ObjectTypeDefinitionNode $objectType) {
            return NodeFactory::objectType(NodeValue::init($objectType));
        })->toArray();
    }

    /**
     * Set input types.
     *
     * @param DocumentNode $document
     *
     * @return array
     */
    public function getInputTypes(DocumentNode $document)
    {
        return $this->definitions($document)->filter(function ($def) {
            return $def instanceof InputObjectTypeDefinitionNode;
        })->map(function (InputObjectTypeDefinitionNode $input) {
            return NodeFactory::inputObjectType(NodeValue::init($input));
        })->toArray();
    }

    /**
     * Set mutation fields.
     *
     * @param DocumentNode $document
     *
     * @return array
     */
    public function getMutations(DocumentNode $document)
    {
        $mutationNode = $this->objectTypes($document)
            ->first(function (ObjectTypeDefinitionNode $objectType) {
                return 'Mutation' === $objectType->name->value;
            });

        if (! $mutationNode) {
            return [];
        }

        return collect($mutationNode->fields)
        ->mapWithKeys(function (FieldDefinitionNode $mutation) use ($mutationNode) {
            return [data_get($mutation, 'name.value') => MutationFactory::resolve($mutation, $mutationNode)];
        })->toArray();
    }

    /**
     * Set query fields.
     *
     * @param DocumentNode $document
     *
     * @return array
     */
    public function getQueries(DocumentNode $document)
    {
        $queryNode = $this->objectTypes($document)
            ->first(function (ObjectTypeDefinitionNode $objectType) {
                return 'Query' === $objectType->name->value;
            });

        if (! $queryNode) {
            return [];
        }

        return collect($queryNode->fields)
        ->mapWithKeys(function (FieldDefinitionNode $query) use ($queryNode) {
            return [data_get($query, 'name.value') => QueryFactory::resolve($query, $queryNode)];
        })->toArray();
    }

    /**
     * Get definitions from document.
     *
     * @param DocumentNode $document
     *
     * @return \Illuminate\Support\Collection
     */
    protected function definitions(DocumentNode $document)
    {
        return collect($document->definitions);
    }

    /**
     * Get object types from document.
     *
     * @param DocumentNode $document
     *
     * @return \Illuminate\Support\Collection
     */
    protected function objectTypes(DocumentNode $document)
    {
        return $this->definitions($document)->filter(function ($def) {
            return $def instanceof ObjectTypeDefinitionNode;
        });
    }
}

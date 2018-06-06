<?php

namespace Nuwave\Lighthouse\Schema\Utils;


use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Language\Parser;
use Illuminate\Support\Collection;

class DocumentAST
{
    /**
     * @var DocumentNode $documentNode
     */
    protected $documentNode;

    public function __construct(DocumentNode $documentNode)
    {
        $this->documentNode = $documentNode;
    }

    /**
     * Create a new instance from a schema.
     *
     * @param $schema
     * @return DocumentAST
     */
    public static function parse($schema)
    {
        return new static(Parser::parse($schema));
    }

    /**
     * Parse a single object type.
     *
     * @param string $definition
     *
     * @return ObjectTypeDefinitionNode
     * @throws \Exception
     */
    public static function parseObjectType($definition)
    {
        $objectTypes = self::parse($definition)->objectTypes();
        if ($objectTypes->count() <> 1) {
            throw new \Exception('More than one definition was found in the passed in schema.');
        }
        return $objectTypes->first();
    }

    /**
     * Parse a single field definition.
     *
     * @param $fieldDefinition
     * @return FieldDefinitionNode
     *
     * @throws \Exception
     */
    public static function parseFieldDefinition($fieldDefinition)
    {
        return self::parseObjectType("type Dummy { $fieldDefinition }")
            ->fields[0];
    }

    /**
     * Parse the definition for arguments on a field.
     *
     * @param string $argumentDefinitions
     * @return NodeList
     *
     * @throws \Exception
     */
    public static function parseArgumentDefinitions($argumentDefinitions)
    {
        return self::parseFieldDefinition("field($argumentDefinitions): String")
            ->arguments;
    }

    /**
     * Parse the definition of a type name.
     *
     * @param string $typeDefinition
     *
     * @return TypeNode
     * @throws \Exception
     */
    public static function parseTypeDefinition($typeDefinition)
    {
        return self::parseFieldDefinition("dummy: $typeDefinition")->type;
    }

    /**
     * Parse the definition for directives.
     *
     * @param string $directiveDefinition
     *
     * @return NodeList
     * @throws \Exception
     */
    public static function parseDirectives($directiveDefinition)
    {
        return self::parseObjectType("type Dummy $directiveDefinition {}")->directives;
    }



    /**
     * Get a collection of the contained definitions.
     *
     * @return Collection
     */
    public function definitions()
    {
        return collect($this->documentNode->definitions);
    }

    /**
     * Get all definitions for directives.
     *
     * @return Collection
     */
    public function directives()
    {
        return $this->getDefinitionsByType(DirectiveDefinitionNode::class);
    }

    /**
     * Get all definitions for type extensions.
     *
     * @return Collection
     */
    public function typeExtensions()
    {
        return $this->getDefinitionsByType(TypeExtensionDefinitionNode::class);
    }

    /**
     * Get all definitions for operations.
     *
     * @return Collection
     */
    public function operations()
    {
        return $this->getDefinitionsByType(OperationDefinitionNode::class);
    }

    /**
     * Get all definitions for object types.
     *
     * @return Collection
     */
    public function objectTypes()
    {
        return $this->getDefinitionsByType(ObjectTypeDefinitionNode::class);
    }

    /**
     * Get the root query type definition.
     *
     * @return ObjectTypeDefinitionNode
     */
    public function getQueryTypeDefinition()
    {
        return $this->getObjectTypeOrDefault('Query');
    }

    /**
     * Get the root mutation type definition.
     *
     * @return ObjectTypeDefinitionNode
     */
    public function getMutationTypeDefinition()
    {
        return $this->getObjectTypeOrDefault('Mutation');
    }

    /**
     * Get the root subscription type definition.
     *
     * @return ObjectTypeDefinitionNode
     */
    public function getSubscriptionTypeDefinition()
    {
        return $this->getObjectTypeOrDefault('Subscription');
    }

    /**
     * Either get an existing definition or an empty type definition.
     *
     * @param string $name
     *
     * @return ObjectTypeDefinitionNode
     */
    public function getObjectTypeOrDefault($name)
    {
        return $this->getObjectType($name)
            ?: self::parseObjectType('type ' . $name . '{}');
    }

    /**
     * @param string $name
     *
     * @return ObjectTypeDefinitionNode|null
     */
    public function getObjectType($name)
    {
        return $this->objectTypes()->first(function (ObjectTypeDefinitionNode $objectType) use ($name) {
            return $objectType->name->value === $name;
        });
    }

    /**
     * @param $name
     *
     * @return TypeExtensionDefinitionNode|null
     */
    public function getTypeExtension($name)
    {
        return $this->typeExtensions()->first(function (TypeExtensionDefinitionNode $typeExtension) use ($name) {
            return $typeExtension->definition->name->value === $name;
        });
    }

    /**
     * @param string $type
     *
     * @return Collection
     */
    public function getDefinitionsByType($type)
    {
        return $this->definitions()->filter(function ($node) use ($type) {
            return $node instanceof $type;
        });
    }

    /**
     * @param ObjectTypeDefinitionNode $objectType
     * @param FieldDefinitionNode $field
     *
     * @return ObjectTypeDefinitionNode
     */
    public static function addFieldToObjectType(ObjectTypeDefinitionNode $objectType, FieldDefinitionNode $field)
    {
        // webonyx/graphql-php is inconsistent here
        // This should be FieldDefinitionNode[] but comes back as NodeList
        /** @var NodeList $nodeList */
        $nodeList = $objectType->fields;

        $objectType->fields = $nodeList->merge([$field]);

        return $objectType;
    }

    public function addFieldToQueryType(FieldDefinitionNode $field)
    {
        $query = $this->getQueryTypeDefinition();
        $query = self::addFieldToObjectType($query, $field);
        $this->setObjectType($query);
    }

    /**
     * @param ObjectTypeDefinitionNode $definitionNode
     *
     * @return DocumentAST
     */
    public function setObjectType(ObjectTypeDefinitionNode $definitionNode)
    {
        $name = $definitionNode->name->value;
        $newDefinitions = $this->definitions()
            ->reject(function ($node) use ($name) {
                return $node instanceof ObjectTypeDefinitionNode && $node->name->value === $name;
            })->push($definitionNode)
            // Reindex, otherwise offset errors might happen in subsequent runs
            ->values()
            ->all();

        // This was a NodeList before, so put it back as it was
        $this->documentNode->definitions = new NodeList($newDefinitions);
//        $definitions->merge()
//
//        $this->documentNode->definitions = $this->objectTypes()->reject(function (ObjectTypeDefinitionNode $type) use ($name) {
//            return $type->name->value === $name;
//        })->push($definitionNode)->toArray();

        return $this;
    }

    /**
     * @param string $definition
     *
     * @return static
     *
     * @throws \Exception
     */
    public function setObjectTypeFromString($definition)
    {
        $definitionNode = self::parseObjectType($definition);
        $this->setObjectType($definitionNode);

        return $this;
    }
}

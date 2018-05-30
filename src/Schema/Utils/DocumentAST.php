<?php

namespace Nuwave\Lighthouse\Schema\Utils;


use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
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
     * Get a collection of the contained definitions.
     *
     * @return Collection
     */
    public function definitions()
    {
        return collect($this->documentNode->definitions);
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
            ?: self::parseSingleDefinition('type ' . $name . '{}');
    }

    /**
     * Parse and return a single definition.
     *
     * @param string $definition
     *
     * @return DefinitionNode
     * @throws \Exception
     */
    public static function parseSingleDefinition($definition)
    {
        $definitions = self::parse($definition)->definitions();
        if ($definitions->count() <> 1) {
            throw new \Exception('More than one definition was found in the passed in schema.');
        }
        return $definitions->first();
    }

    /**
     * @param string $name
     *
     * @return ObjectTypeDefinitionNode|null
     */
    public function getObjectType($name)
    {
        $definition = $this->getDefinitionByName($name);

        return $definition instanceof ObjectTypeDefinitionNode ? $definition : null;
    }

    /**
     * @param string $name
     *
     * @return DefinitionNode|null
     */
    public function getDefinitionByName($name)
    {
        return $this->definitions()->first(function ($node) use ($name) {
            return $node->name->value === $name;
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
        $this->setDefinition($query);
    }


    public static function parseFieldDefinition($fieldDefinition)
    {
        return self::parseSingleDefinition("type Dummy { $fieldDefinition }")
            ->fields[0];
    }

    /**
     * @param string $argumentDefinitions
     * @return NodeList
     */
    public static function parseArgumentDefinitions($argumentDefinitions)
    {
        return self::parseFieldDefinition("field($argumentDefinitions): String }")
            ->arguments;
    }

    /**
     * @param DefinitionNode $definitionNode
     *
     * @return DocumentAST
     */
    public function setDefinition(DefinitionNode $definitionNode)
    {
        $name = $definitionNode->name->value;

        $this->documentNode->definitions = $this->definitions()->reject(function (DefinitionNode $type) use ($name) {
            return $type->name->value === $name;
        })->push($definitionNode)->toArray();

        return $this;
    }

    public function setDefinitionFromString($definition)
    {
        $definitionNode = self::parseSingleDefinition($definition);
        $this->setDefinition($definitionNode);

        return $this;
    }

    /**
     * @return Collection
     */
    public function directives()
    {
        return $this->getDefinitionsByType(DirectiveDefinitionNode::class);
    }

    public function typeExtensions()
    {
        return $this->getDefinitionsByType(TypeExtensionDefinitionNode::class);
    }

    public function operations()
    {
        return $this->getDefinitionsByType(OperationDefinitionNode::class);
    }
}

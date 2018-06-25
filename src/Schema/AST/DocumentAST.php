<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Support\Collection;

class DocumentAST
{
    /**
     * @var DocumentNode
     */
    protected $documentNode;

    /**
     * DocumentAST constructor.
     *
     * @param DocumentNode $documentNode
     */
    public function __construct(DocumentNode $documentNode)
    {
        $this->documentNode = $documentNode;
    }

    /**
     * Create a new instance from a schema.
     *
     * @param $schema
     *
     * @return DocumentAST
     */
    public static function fromSource($schema)
    {
        return new static(Parser::parse($schema));
    }

    /**
     * Get instance of underlining document node.
     *
     * @return DocumentNode
     */
    public function documentNode()
    {
        return ASTHelper::cloneNode($this->documentNode);
    }

    /**
     * Get a collection of the contained definitions.
     *
     * @return Collection
     */
    public function definitions()
    {
        return collect($this->documentNode->definitions)->map(function (Node $node) {
            $clone = ASTHelper::cloneNode($node);
            $clone->spl_object_hash = spl_object_hash($node);
            if ($node instanceof TypeExtensionDefinitionNode) {
                $clone->definition->spl_object_hash = spl_object_hash($node->definition);
            }

            return $clone;
        });
    }

    /**
     * Get all type definitions from the document.
     *
     * @return Collection
     */
    public function typeDefinitions()
    {
        return $this->definitions()->filter(function (DefinitionNode $node) {
            return $node instanceof ScalarTypeDefinitionNode
                || $node instanceof ObjectTypeDefinitionNode
                || $node instanceof InterfaceTypeDefinitionNode
                || $node instanceof UnionTypeDefinitionNode
                || $node instanceof EnumTypeDefinitionNode
                || $node instanceof InputObjectTypeDefinitionNode;
        });
    }

    /**
     * Get all definitions for directives.
     *
     * @return Collection
     */
    public function directives()
    {
        return $this->definitionsByType(DirectiveDefinitionNode::class);
    }

    /**
     * Get all definitions for type extensions.
     *
     * Without a name, it simply return all TypeExtensions.
     * If a name is given, it may return multiple type extensions
     * that apply to a named type.
     *
     * @param string|null $extendedTypeName
     *
     * @return Collection
     */
    public function typeExtensions($extendedTypeName = null)
    {
        return $this->definitionsByType(TypeExtensionDefinitionNode::class)
            ->filter(function (TypeExtensionDefinitionNode $typeExtension) use ($extendedTypeName) {
                return is_null($extendedTypeName) || $extendedTypeName === $typeExtension->definition->name->value;
            });
    }

    /**
     * Get all definitions for operations.
     *
     * @return Collection
     */
    public function operations()
    {
        return $this->definitionsByType(OperationDefinitionNode::class);
    }

    /**
     * Get all fragment definitions.
     *
     * @return Collection
     */
    public function fragments()
    {
        return $this->definitionsByType(FragmentDefinitionNode::class);
    }

    /**
     * Get all definitions for object types.
     *
     * @return Collection
     */
    public function objectTypes()
    {
        return $this->definitionsByType(ObjectTypeDefinitionNode::class);
    }

    /**
     * Get all interface definitions.
     *
     * @return Collection
     */
    public function interfaces()
    {
        return $this->definitionsByType(InterfaceTypeDefinitionNode::class);
    }

    /**
     * Get the root query type definition.
     *
     * @return ObjectTypeDefinitionNode
     */
    public function queryType()
    {
        return $this->objectTypeOrDefault('Query');
    }

    /**
     * Get the root mutation type definition.
     *
     * @return ObjectTypeDefinitionNode
     */
    public function mutationType()
    {
        return $this->objectTypeOrDefault('Mutation');
    }

    /**
     * Get the root subscription type definition.
     *
     * @return ObjectTypeDefinitionNode
     */
    public function subscriptionType()
    {
        return $this->objectTypeOrDefault('Subscription');
    }

    /**
     * Either get an existing definition or an empty type definition.
     *
     * @param string $name
     *
     * @return ObjectTypeDefinitionNode
     */
    protected function objectTypeOrDefault($name)
    {
        return $this->objectType($name)
            ?: PartialParser::objectTypeDefinition('type '.$name.'{}');
    }

    /**
     * @param string $name
     *
     * @return ObjectTypeDefinitionNode|null
     */
    public function objectType($name)
    {
        return $this->objectTypes()->first(function (ObjectTypeDefinitionNode $objectType) use ($name) {
            return $objectType->name->value === $name;
        });
    }

    /**
     * @param string $type
     *
     * @return Collection
     */
    protected function definitionsByType($type)
    {
        return $this->definitions()->filter(function ($node) use ($type) {
            return $node instanceof $type;
        });
    }

    /**
     * Add a single field to the query type.
     *
     * @param FieldDefinitionNode $field
     *
     * @return $this
     */
    public function addFieldToQueryType(FieldDefinitionNode $field)
    {
        $query = $this->queryType();
        $query->fields = $query->fields->merge([$field]);
        $this->setDefinition($query);

        return $this;
    }

    /**
     * @param DefinitionNode $definition
     *
     * @return DocumentAST
     */
    public function setDefinition(DefinitionNode $definition)
    {
        $found = false;

        $newDefinitions = $this->originalDefinitions()
            ->map(function (DefinitionNode $node) use ($definition, &$found) {
                if (! $hashID = data_get($definition, 'spl_object_hash')) {
                    // We didn't clone the new definition
                    return $node;
                }

                $compareID = $node instanceof TypeExtensionDefinitionNode
                    ? spl_object_hash($node->definition)
                    : spl_object_hash($node);

                if ($compareID === $hashID) {
                    $found = true;

                    if ($node instanceof TypeExtensionDefinitionNode) {
                        $node->definition = $definition;
                    } else {
                        $node = $definition;
                    }
                }

                return $node;
            })
            ->unless($found, function ($definitions) use ($definition) {
                return $definitions->push($definition);
            })
            // Reindex, otherwise offset errors might happen in subsequent runs
            ->values()
            ->all();

        // This was a NodeList before, so put it back as it was
        $this->documentNode->definitions = new NodeList($newDefinitions);

        return $this;
    }

    /**
     * @param string $definition
     *
     * @throws \Exception
     *
     * @return static
     */
    public function setObjectTypeFromString($definition)
    {
        $objectType = self::parseObjectType($definition);
        $this->setDefinition($objectType);

        return $this;
    }

    /**
     * Get a collection of the contained definitions.
     *
     * @return Collection
     */
    protected function originalDefinitions()
    {
        return collect($this->documentNode->definitions);
    }
}

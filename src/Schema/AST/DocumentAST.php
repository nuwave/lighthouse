<?php

declare(strict_types=1);

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
     * Check if documentAST is currently locked.
     *
     * @var bool
     */
    protected $locked = false;

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
     * Create a new DocumentAST instance from a schema.
     *
     * @param string $schema
     *
     * @return DocumentAST
     */
    public static function fromSource(string $schema): DocumentAST
    {
        return new static(Parser::parse($schema));
    }

    /**
     * Mark the AST as locked.
     *
     * @return self
     */
    public function lock()
    {
        $this->locked = true;

        return $this;
    }

    /**
     * Mark the AST as unlocked.
     *
     * @return self
     */
    public function unlock()
    {
        $this->locked = false;

        return $this;
    }

    /**
     * Get an instance of the underlying document node.
     *
     * @return DocumentNode
     */
    public function document(): DocumentNode
    {
        return ASTHelper::cloneNode($this->documentNode);
    }

    /**
     * Get a collection of the contained definitions.
     *
     * @return Collection
     */
    public function definitions(): Collection
    {
        $definitions = collect($this->documentNode->definitions);

        return $this->locked ? $definitions : $definitions->map(function (Node $node) {
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
    public function typeDefinitions(): Collection
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
    public function directiveDefinitions(): Collection
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
    public function typeExtensionDefinitions($extendedTypeName = null): Collection
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
    public function operationDefinitions(): Collection
    {
        return $this->definitionsByType(OperationDefinitionNode::class);
    }

    /**
     * Get all fragment definitions.
     *
     * @return Collection
     */
    public function fragmentDefinitions(): Collection
    {
        return $this->definitionsByType(FragmentDefinitionNode::class);
    }

    /**
     * Get all definitions for object types.
     *
     * @return Collection
     */
    public function objectTypeDefinitions(): Collection
    {
        return $this->definitionsByType(ObjectTypeDefinitionNode::class);
    }

    /**
     * Get a single object type definition by name.
     *
     * @param string $name
     *
     * @return ObjectTypeDefinitionNode|null
     */
    public function objectTypeDefinition(string $name)
    {
        return $this->objectTypeDefinitions()->first(function (ObjectTypeDefinitionNode $objectType) use ($name) {
            return $objectType->name->value === $name;
        });
    }

    /**
     * @return Collection
     */
    public function inputObjectTypeDefinitions(): Collection
    {
        return $this->definitionsByType(InputObjectTypeDefinitionNode::class);
    }

    /**
     * @param string $name
     *
     * @return InputObjectTypeDefinitionNode|null
     */
    public function inputObjectTypeDefinition(string $name)
    {
        return $this->inputObjectTypeDefinitions()->first(function (InputObjectTypeDefinitionNode $inputType) use ($name) {
            return $inputType->name->value === $name;
        });
    }

    /**
     * Get all interface definitions.
     *
     * @return Collection
     */
    public function interfaceDefinitions(): Collection
    {
        return $this->definitionsByType(InterfaceTypeDefinitionNode::class);
    }

    /**
     * Get the root query type definition.
     *
     * @return ObjectTypeDefinitionNode
     */
    public function queryTypeDefinition(): ObjectTypeDefinitionNode
    {
        return $this->objectTypeOrDefault('Query');
    }

    /**
     * Get the root mutation type definition.
     *
     * @return ObjectTypeDefinitionNode
     */
    public function mutationTypeDefinition(): ObjectTypeDefinitionNode
    {
        return $this->objectTypeOrDefault('Mutation');
    }

    /**
     * Get the root subscription type definition.
     *
     * @return ObjectTypeDefinitionNode
     */
    public function subscriptionTypeDefinition(): ObjectTypeDefinitionNode
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
    protected function objectTypeOrDefault(string $name): ObjectTypeDefinitionNode
    {
        return $this->objectTypeDefinition($name)
            ?? PartialParser::objectTypeDefinition('type '.$name.'{}');
    }

    /**
     * Get all definitions of a.
     *
     * @param string $typeClassName
     *
     * @return Collection
     */
    protected function definitionsByType(string $typeClassName): Collection
    {
        return $this->definitions()->filter(function (Node $node) use ($typeClassName) {
            return $node instanceof $typeClassName;
        });
    }

    /**
     * Add a single field to the query type.
     *
     * @param FieldDefinitionNode $field
     *
     * @return DocumentAST
     */
    public function addFieldToQueryType(FieldDefinitionNode $field): DocumentAST
    {
        $query = $this->queryTypeDefinition();
        $query->fields = ASTHelper::mergeNodeList($query->fields, [$field]);

        $this->setDefinition($query);

        return $this;
    }

    /**
     * @param DefinitionNode $newDefinition
     *
     * @return DocumentAST
     */
    public function setDefinition(DefinitionNode $newDefinition): DocumentAST
    {
        $originalDefinitions = collect($this->documentNode->definitions);

        if (! $newHashID = data_get($newDefinition, 'spl_object_hash')) {
            // This means the new definition is not a clone, so we do
            // not have to look for an existing definition to replace
            $newDefinitions = $originalDefinitions->push($newDefinition);
        } else {
            $found = false;

            $newDefinitions = $originalDefinitions->map(function (DefinitionNode $originalDefinition) use ($newDefinition, $newHashID, &$found) {
                $originalHashID = $originalDefinition instanceof TypeExtensionDefinitionNode
                    ? spl_object_hash($originalDefinition->definition)
                    : spl_object_hash($originalDefinition);

                if ($originalHashID === $newHashID) {
                    $found = true;

                    if ($originalDefinition instanceof TypeExtensionDefinitionNode) {
                        $originalDefinition->definition = $newDefinition;
                    } else {
                        $originalDefinition = $newDefinition;
                    }
                }

                return $originalDefinition;
            });

            if (! $found) {
                $newDefinitions = $newDefinitions->push($newDefinition);
            }
        }

        $newDefinitions = $newDefinitions
            // Reindex, otherwise offset errors might happen in subsequent runs
            ->values()
            ->all();

        // This was a NodeList before, so put it back as it was
        $this->documentNode->definitions = new NodeList($newDefinitions);

        return $this;
    }
}

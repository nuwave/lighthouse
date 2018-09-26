<?php

declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Utils\AST;
use GraphQL\Language\Parser;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DocumentASTException;

class DocumentAST implements \Serializable
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
        // Ignore location since it only bloats the AST
        return new static(Parser::parse($schema, ['noLocation' => true]));
    }

    /**
     * Strip out irrelevant information to make serialization more efficient.
     */
    public function serialize()
    {
        return serialize(AST::toArray($this->documentNode));
    }

    /**
     * Construct from the string representation.
     *
     * @param $serialized
     */
    public function unserialize($serialized)
    {
        $this->documentNode = AST::fromArray(unserialize($serialized));
    }

    /**
     * Mark the AST as locked.
     *
     * @return DocumentAST
     */
    public function lock(): DocumentAST
    {
        $this->locked = true;

        return $this;
    }

    /**
     * Mark the AST as unlocked.
     *
     * @return DocumentAST
     */
    public function unlock(): DocumentAST
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

        return $this->locked
            ? $definitions
            : $definitions->map(function (Node $node) {
                $clone = ASTHelper::cloneNode($node);

                return $this->assignDefinitionNodeHash($clone, $node);
            });
    }

    /**
     * Get all type definitions from the document.
     *
     * @return Collection
     */
    public function typeDefinitions(): Collection
    {
        return $this->definitions()
            ->filter(function (DefinitionNode $node) {
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
    public function typeExtensionDefinitions(string $extendedTypeName = null): Collection
    {
        return $this->definitionsByType(TypeExtensionNode::class)
            ->filter(function (TypeExtensionNode $typeExtension) use ($extendedTypeName) {
                return is_null($extendedTypeName) || $extendedTypeName === $typeExtension->name->value;
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
        return $this->objectTypeDefinitions()
            ->first(function (ObjectTypeDefinitionNode $objectType) use ($name) {
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
        return $this->inputObjectTypeDefinitions()
            ->first(function (InputObjectTypeDefinitionNode $inputType) use ($name) {
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
        return $this->definitions()
            ->filter(function (Node $node) use ($typeClassName) {
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
        if ($this->locked) {
            $nodeName = data_get($newDefinition, 'name.value', 'Node');
            $message = "{$nodeName} cannot be added to the DocumentAST while it is locked.";
            throw new DocumentASTException($message);
        }

        $originalDefinitions = collect($this->documentNode->definitions);

        if (! $newHashID = data_get($newDefinition, 'spl_object_hash')) {
            // This means the new definition is not a clone, so we do
            // not have to look for an existing definition to replace
            $newDefinitions = $originalDefinitions->push($newDefinition);
        } else {
            $found = false;

            $newDefinitions = $originalDefinitions->map(function (DefinitionNode $originalDefinition) use ($newDefinition, $newHashID, &$found) {
                $originalHashID = $this->getDefinitionNodeHash($originalDefinition);

                if ($originalHashID === $newHashID) {
                    $found = true;
                    $newDefinition->spl_object_hash = $originalHashID;

                    $originalDefinition = $newDefinition;
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

    /**
     * Get node's original/current hash.
     *
     * @param DefinitionNode $node
     *
     * @return string
     */
    protected function getDefinitionNodeHash(DefinitionNode $node): string
    {
        return data_get($node, 'spl_object_hash', spl_object_hash($node));
    }

    /**
     * Assign definition node(s) a hash.
     *
     * @param DefinitionNode $newDefinition
     * @param DefinitionNode $currentDefinition
     *
     * @return DefinitionNode
     */
    protected function assignDefinitionNodeHash(
        DefinitionNode $newDefinition,
        DefinitionNode $currentDefinition = null
    ): DefinitionNode {
        $newDefinition->spl_object_hash = data_get(
            $currentDefinition,
            'spl_object_hash',
            spl_object_hash($currentDefinition)
        );

        return $newDefinition;
    }
}

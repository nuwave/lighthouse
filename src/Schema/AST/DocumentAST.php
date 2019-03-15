<?php

namespace Nuwave\Lighthouse\Schema\AST;

use Serializable;
use GraphQL\Utils\AST;
use GraphQL\Language\Parser;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\Node;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\ParseException;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;

class DocumentAST implements Serializable
{
    /**
     * A map from definition name to the definition node.
     *
     * @var \GraphQL\Type\Definition\ObjectType<\GraphQL\Language\AST\DefinitionNode>
     */
    protected $definitionMap;

    /**
     * A collection of type extensions.
     *
     * @var \Illuminate\Support\Collection<\GraphQL\Language\AST\TypeExtensionNode>
     */
    protected $typeExtensionsMap;

    /**
     * @param  \GraphQL\Language\AST\DocumentNode  $documentNode
     * @return void
     */
    public function __construct(DocumentNode $documentNode)
    {
        /** @var \Illuminate\Support\Collection<\GraphQL\Language\AST\TypeExtensionNode> $typeExtensions */
        /** @var \Illuminate\Support\Collection<\GraphQL\Language\AST\DefinitionNode> $definitionNodes */
        // We can not store type extensions in the map, since they do not have unique names
        [$typeExtensions, $definitionNodes] = (new Collection($documentNode->definitions))
            ->partition(function (DefinitionNode $definitionNode): bool {
                return $definitionNode instanceof TypeExtensionNode;
            });

        $this->typeExtensionsMap = $typeExtensions
            ->mapWithKeys(function (TypeExtensionNode $node): array {
                return [$this->typeExtensionUniqueKey($node) => $node];
            });

        $this->definitionMap = $definitionNodes
            ->mapWithKeys(function (DefinitionNode $node): array {
                return [$node->name->value => $node];
            });
    }

    /**
     * Return a unique key that identifies a type extension.
     *
     * @param  \GraphQL\Language\AST\TypeExtensionNode  $typeExtensionNode
     * @return string
     */
    protected function typeExtensionUniqueKey(TypeExtensionNode $typeExtensionNode): string
    {
        $fieldNames = (new Collection($typeExtensionNode->fields))
            ->map(function ($field): string {
                return $field->name->value;
            })
            ->implode(':');

        return $typeExtensionNode->name->value.$fieldNames;
    }

    /**
     * Create a new DocumentAST instance from a schema.
     *
     * @param  string  $schema
     * @return static
     *
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     */
    public static function fromSource(string $schema): self
    {
        try {
            return new static(
                Parser::parse(
                    $schema,
                    // Ignore location since it only bloats the AST
                    ['noLocation' => true]
                )
            );
        } catch (SyntaxError $syntaxError) {
            // Throw our own error class instead, since otherwise a schema definition
            // error would get rendered to the Client.
            throw new ParseException(
                $syntaxError->getMessage()
            );
        }
    }

    /**
     * Strip out irrelevant information to make serialization more efficient.
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize(
            $this->definitionMap
                ->mapWithKeys(function (DefinitionNode $node, string $key): array {
                    return [$key => AST::toArray($node)];
                })
        );
    }

    /**
     * Construct from the string representation.
     *
     * @param  string  $serialized
     * @return void
     */
    public function unserialize($serialized): void
    {
        $this->definitionMap = unserialize($serialized)
            ->mapWithKeys(function (array $node, string $key): array {
                return [$key => AST::fromArray($node)];
            });
    }

    /**
     * Get all type definitions from the document.
     *
     * @return \Illuminate\Support\Collection<\GraphQL\Language\AST\TypeDefinitionNode>
     */
    public function typeDefinitions(): Collection
    {
        return $this->definitionMap
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
     * @return \Illuminate\Support\Collection<\GraphQL\Language\AST\DirectiveDefinitionNode>
     */
    public function directiveDefinitions(): Collection
    {
        return $this->definitionsByType(DirectiveDefinitionNode::class);
    }

    /**
     * Get all extensions that apply to a named type.
     *
     * @param  string  $extendedTypeName
     * @return \Illuminate\Support\Collection<\GraphQL\Language\AST\TypeExtensionNode>
     */
    public function extensionsForType(string $extendedTypeName): Collection
    {
        return $this->typeExtensionsMap
            ->filter(function (TypeExtensionNode $typeExtension) use ($extendedTypeName): bool {
                return $extendedTypeName === $typeExtension->name->value;
            });
    }

    /**
     * Return all the type extensions.
     *
     * @return \Illuminate\Support\Collection<\GraphQL\Language\AST\TypeExtensionNode>
     */
    public function typeExtensions(): Collection
    {
        return $this->typeExtensionsMap;
    }

    /**
     * Get all definitions for object types.
     *
     * @return \Illuminate\Support\Collection<\GraphQL\Language\AST\ObjectTypeDefinitionNode>
     */
    public function objectTypeDefinitions(): Collection
    {
        return $this->definitionsByType(ObjectTypeDefinitionNode::class);
    }

    /**
     * Get a single object type definition by name.
     *
     * @param  string  $name
     * @return \GraphQL\Language\AST\ObjectTypeDefinitionNode|null
     */
    public function objectTypeDefinition(string $name): ?ObjectTypeDefinitionNode
    {
        return $this->objectTypeDefinitions()
            ->first(function (ObjectTypeDefinitionNode $objectType) use ($name): bool {
                return $objectType->name->value === $name;
            });
    }

    /**
     * @return \Illuminate\Support\Collection<\GraphQL\Language\AST\InputObjectTypeDefinitionNode>
     */
    public function inputObjectTypeDefinitions(): Collection
    {
        return $this->definitionsByType(InputObjectTypeDefinitionNode::class);
    }

    /**
     * @param  string  $name
     * @return \GraphQL\Language\AST\InputObjectTypeDefinitionNode|null
     */
    public function inputObjectTypeDefinition(string $name): ?InputObjectTypeDefinitionNode
    {
        return $this->inputObjectTypeDefinitions()
            ->first(function (InputObjectTypeDefinitionNode $inputType) use ($name): bool {
                return $inputType->name->value === $name;
            });
    }

    /**
     * Get all interface definitions.
     *
     * @return \Illuminate\Support\Collection<\GraphQL\Language\AST\InterfaceTypeDefinitionNode>
     */
    public function interfaceDefinitions(): Collection
    {
        return $this->definitionsByType(InterfaceTypeDefinitionNode::class);
    }

    /**
     * Get the root query type definition.
     *
     * @return \GraphQL\Language\AST\ObjectTypeDefinitionNode|null
     */
    public function queryTypeDefinition(): ?ObjectTypeDefinitionNode
    {
        return $this->objectTypeDefinition('Query');
    }

    /**
     * Get the root mutation type definition.
     *
     * @return \GraphQL\Language\AST\ObjectTypeDefinitionNode|null
     */
    public function mutationTypeDefinition(): ?ObjectTypeDefinitionNode
    {
        return $this->objectTypeDefinition('Mutation');
    }

    /**
     * Get the root subscription type definition.
     *
     * @return \GraphQL\Language\AST\ObjectTypeDefinitionNode|null
     */
    public function subscriptionTypeDefinition(): ?ObjectTypeDefinitionNode
    {
        return $this->objectTypeDefinition('Subscription');
    }

    /**
     * Get all definitions of a given type.
     *
     * @param  string  $typeClassName
     * @return \Illuminate\Support\Collection
     */
    protected function definitionsByType(string $typeClassName): Collection
    {
        return $this->definitionMap
            ->filter(function (Node $node) use ($typeClassName) {
                return $node instanceof $typeClassName;
            });
    }

    /**
     * Add a single field to the query type.
     *
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $field
     * @return $this
     */
    public function addFieldToQueryType(FieldDefinitionNode $field): self
    {
        $query = $this->queryTypeDefinition();
        $query->fields = ASTHelper::mergeNodeList($query->fields, [$field]);

        $this->setDefinition($query);

        return $this;
    }

    /**
     * @param  \GraphQL\Language\AST\DefinitionNode  $newDefinition
     * @return $this
     */
    public function setDefinition(DefinitionNode $newDefinition): self
    {
        if ($newDefinition instanceof TypeExtensionNode) {
            $this->typeExtensionsMap->put(
                $this->typeExtensionUniqueKey($newDefinition),
                $newDefinition
            );
        } else {
            $this->definitionMap->put(
                $newDefinition->name->value,
                $newDefinition
            );
        }

        return $this;
    }
}

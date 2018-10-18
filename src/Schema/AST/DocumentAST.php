<?php

declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Utils\AST;
use GraphQL\Language\Parser;
use GraphQL\Language\AST\Node;
use GraphQL\Error\SyntaxError;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Exceptions\ParseException;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;

class DocumentAST implements \Serializable
{
    /**
     * A map from definition name to the definition node.
     *
     * @var Collection
     */
    protected $definitionMap;
    /**
     * A collection of type extensions.
     *
     * @var Collection
     */
    protected $typeExtensions;
    
    /**
     * @param DocumentNode $documentNode
     */
    public function __construct(DocumentNode $documentNode)
    {
        // We can not store type extensions in the map, since they do not have unique names
        list($this->typeExtensions, $definitionNodes) = collect($documentNode->definitions)
            ->partition(function(DefinitionNode $definitionNode){
                return $definitionNode instanceof TypeExtensionNode;
            });
        
        $this->definitionMap = $definitionNodes
            ->mapWithKeys(function(DefinitionNode $node){
               return [$node->name->value => $node];
            });
    }
    
    /**
     * Create a new DocumentAST instance from a schema.
     *
     * @param string $schema
     *
     * @throws ParseException
     *
     * @return DocumentAST
     */
    public static function fromSource(string $schema): DocumentAST
    {
        try{
            return new static(
                Parser::parse(
                    $schema,
                    // Ignore location since it only bloats the AST
                    ['noLocation' => true]
                )
            );
        } catch (SyntaxError $syntaxError){
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
        return \serialize([
            'definitionMap' => $this->definitionMap
                ->mapWithKeys(function(DefinitionNode $node, string $key){
                    return [$key => AST::toArray($node)];
                }),
            'typeExtensions' => $this->typeExtensions
                ->map(function(TypeExtensionNode $node){
                    return AST::toArray($node);
                })
        ]);
    }

    /**
     * Construct from the string representation.
     *
     * @param $serialized
     *
     * @return void
     */
    public function unserialize($serialized)
    {
        $unserialize = \unserialize($serialized);
        
        $this->definitionMap = $unserialize['definitionMap']
            ->mapWithKeys(function(array $node, string $key){
                return [$key => AST::fromArray($node)];
            });
        $this->typeExtensions = $unserialize['typeExtensions']
            ->map(function(array $node){
                return AST::fromArray($node);
            });
    }

    /**
     * Get all type definitions from the document.
     *
     * @return Collection
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
        if(is_null($extendedTypeName)){
            return $this->typeExtensions;
        }
        
        return $this->typeExtensions
            ->filter(function (TypeExtensionNode $typeExtension) use ($extendedTypeName): bool {
                return $extendedTypeName === $typeExtension->name->value;
            });
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
        return $this->objectTypeDefinition('Query');
    }

    /**
     * Get the root mutation type definition.
     *
     * @return ObjectTypeDefinitionNode|null
     */
    public function mutationTypeDefinition()
    {
        return $this->objectTypeDefinition('Mutation');
    }

    /**
     * Get the root subscription type definition.
     *
     * @return ObjectTypeDefinitionNode|null
     */
    public function subscriptionTypeDefinition()
    {
        return $this->objectTypeDefinition('Subscription');
    }

    /**
     * Get all definitions of a given type.
     *
     * @param string $typeClassName
     *
     * @return Collection
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
        if($newDefinition instanceof TypeExtensionNode){
            if(! $this->typeExtensions->search($newDefinition, true)) {
                $this->typeExtensions->push($newDefinition);
            }
        } else {
            $this->definitionMap->put(
                $newDefinition->name->value,
                $newDefinition
            );
        }
        
        return $this;
    }
}

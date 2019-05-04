<?php

namespace Nuwave\Lighthouse\Schema\AST;

use Exception;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\TypeDefinitionNode;
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
     * ['foo' => FooType]
     *
     * @var NodeList<TypeDefinitionNode>
     */
    public $types = [];

    /**
     * ['foo' => [0 => FooExtension, 1 => FooExtension]]
     *
     * @var NodeListMap<NodeList<TypeExtensionNode>>
     */
    public $typeExtensions = [];

    /**
     * ['foo' => FooDirective]
     *
     * @var NodeList<DirectiveDefinitionNode>
     */
    public $directives = [];

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
            $documentNode = Parser::parse(
                $schema,
                // Ignore location since it only bloats the AST
                ['noLocation' => true]
            );
        } catch (SyntaxError $syntaxError) {
            // Throw our own error class instead, since otherwise a schema definition
            // error would get rendered to the Client.
            throw new ParseException(
                $syntaxError->getMessage()
            );
        }

        $instance = new self;

        foreach($documentNode->definitions as $definition) {
            if($definition instanceof TypeDefinitionNode){
                $instance->types[$definition->name->value] = $definition;
            } elseif ($definition instanceof TypeExtensionNode){
                $instance->typeExtensions[$definition->name->value] []= $definition;
            } elseif($definition instanceof DirectiveDefinitionNode){
                $instance->directives[$definition->name->value] = $definition;
            } else {
                throw new \Exception(
                    'Unknown definition type'
                );
            }
        }

        return $instance;
    }

    public function serialize(): string
    {
        $nodeToArray = function (Node $node) {
            return $node->toArray(true);
        };

        return serialize([
            'types' => array_map($nodeToArray, $this->types),
//            'typeExtensions' => serialize($this->typeExtensions),
            'directives' => array_map($nodeToArray, $this->directives),
        ]);
    }

    public function unserialize($serialized): void
    {
        [
            'types' => $types,
//            'typeExtensions' => unserialize($typeExtensions),
            'directives' => $directives,
        ] = unserialize($serialized);

        // TODO ensure named offsets
        $this->types = new NodeList($types);
        $this->directives = new NodeList($directives);
    }

    /**
     * @param  \GraphQL\Language\AST\TypeDefinitionNode  $type
     * @return $this
     */
    public function setType(TypeDefinitionNode $type): self
    {
        $this->types[$type->name->value] = $type;

        return $this;
    }

    /**
     * @param  \GraphQL\Language\AST\Node  $definitionNode
     * @return $this
     */
    public function setDefinition(Node $definitionNode): self
    {
        if($definitionNode instanceof DirectiveDefinitionNode){
            $this->directives[$definitionNode->name->value] = $definitionNode;
        } elseif($definitionNode instanceof TypeDefinitionNode){
            $this->types[$definitionNode->name->value] = $definitionNode;
        } else {
            throw new Exception(
                'Unsupported type'
            );
        }

        return $this;
    }

    public function queryTypeDefinition(): ObjectTypeDefinitionNode
    {
        return $this->types['Query'];
    }
}

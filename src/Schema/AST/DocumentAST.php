<?php

namespace Nuwave\Lighthouse\Schema\AST;

use Exception;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Exceptions\ParseException;
use Nuwave\Lighthouse\Schema\Directives\ModelDirective;
use Nuwave\Lighthouse\Support\Utils;
use Serializable;

/**
 * Represents the AST of the entire GraphQL schema document.
 *
 * Explicitly implementing Serializable provides performance gains by:
 * - stripping unnecessary data
 * - leveraging lazy instantiation of schema types
 *
 * @phpstan-type ClassNameToObjectTypeName array<class-string, list<string>>
 * @phpstan-type SerializableDocumentAST array{
 *     types: array<int, array<string, mixed>>,
 *     directives: array<int, array<string, mixed>>,
 *     classNameToObjectTypeName: ClassNameToObjectTypeName,
 * }
 */
class DocumentAST implements Serializable, Arrayable
{
    public const TYPES = 'types';
    public const DIRECTIVES = 'directives';
    public const CLASS_NAME_TO_OBJECT_TYPE_NAME = 'classNameToObjectTypeName';

    /**
     * The types within the schema.
     *
     * ['foo' => FooType].
     *
     * @var \GraphQL\Language\AST\NodeList<\GraphQL\Language\AST\TypeDefinitionNode&\GraphQL\Language\AST\Node>|array<string, \GraphQL\Language\AST\TypeDefinitionNode&\GraphQL\Language\AST\Node>
     */
    public $types = [];

    /**
     * The type extensions within the parsed document.
     *
     * Will NOT be kept after unserialization, as the type
     * extensions are merged with the types before.
     *
     * @var array<string, array<int, \GraphQL\Language\AST\TypeExtensionNode&\GraphQL\Language\AST\Node>>
     */
    public $typeExtensions = [];

    /**
     * Client directive definitions.
     *
     * ['foo' => FooDirective].
     *
     * @var \GraphQL\Language\AST\NodeList<\GraphQL\Language\AST\DirectiveDefinitionNode>|array<string, \GraphQL\Language\AST\DirectiveDefinitionNode>
     */
    public $directives = [];

    /**
     * A map from class names to their respective object types.
     *
     * This is useful for the performant resolution of abstract types.
     *
     * @see \Nuwave\Lighthouse\Schema\TypeRegistry::typeResolverFallback()
     *
     * @var ClassNameToObjectTypeName
     */
    public $classNameToObjectTypeNames = [];

    /**
     * Create a new DocumentAST instance from a schema.
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
            throw new ParseException($syntaxError);
        }

        $instance = new static();

        foreach ($documentNode->definitions as $definition) {
            if ($definition instanceof TypeDefinitionNode) {
                $name = $definition->name->value;

                // Store the types in an associative array for quick lookup
                $instance->types[$name] = $definition;

                if ($definition instanceof ObjectTypeDefinitionNode) {
                    $modelName = ModelDirective::modelClass($definition);
                    if (null === $modelName) {
                        continue;
                    }

                    $namespacesToTry = (array) config('lighthouse.namespaces.models');
                    $modelClass = Utils::namespaceClassName(
                        $modelName,
                        $namespacesToTry,
                        static function (string $classCandidate): bool {
                            return is_subclass_of($classCandidate, Model::class);
                        }
                    );

                    if (null === $modelClass) {
                        $consideredNamespaces = implode(', ', $namespacesToTry);
                        throw new DefinitionException(
                            "Failed to find a model class {$modelName} in namespaces [{$consideredNamespaces}] referenced in @model on type {$name}."
                        );
                    }

                    // It might be valid to have multiple types that correspond to a single model
                    // in order to hide some fields in some scenarios, so we cannot decide on a
                    // single object type for a given class name unambiguously right here.
                    $instance->classNameToObjectTypeNames[$modelClass][] = $name;
                }
            } elseif ($definition instanceof TypeExtensionNode) {
                // Multiple type extensions for the same name can exist
                $instance->typeExtensions[$definition->name->value][] = $definition;
            } elseif ($definition instanceof DirectiveDefinitionNode) {
                $instance->directives[$definition->name->value] = $definition;
            } else {
                throw new Exception('Unknown definition type: ' . get_class($definition));
            }
        }

        return $instance;
    }

    /**
     * Set a type definition in the AST.
     *
     * This operation will overwrite existing definitions with the same name.
     *
     * @param  \GraphQL\Language\AST\TypeDefinitionNode&\GraphQL\Language\AST\Node  $type
     *
     * @return $this
     */
    public function setTypeDefinition(TypeDefinitionNode $type): self
    {
        $this->types[$type->name->value] = $type;

        return $this;
    }

    /**
     * Set a directive definition in the AST.
     *
     * This operation will overwrite existing definitions with the same name.
     *
     * @param  \GraphQL\Language\AST\DirectiveDefinitionNode&\GraphQL\Language\AST\Node  $directive
     */
    public function setDirectiveDefinition(DirectiveDefinitionNode $directive): self
    {
        $this->directives[$directive->name->value] = $directive;

        return $this;
    }

    /**
     * Convert to a serializable array.
     *
     * We exclude the type extensions stored in $typeExtensions,
     * as they are merged with the actual types at this point.
     *
     * @return SerializableDocumentAST
     */
    public function toArray(): array
    {
        return [
            // @phpstan-ignore-next-line Before serialization, those are arrays
            self::TYPES => array_map([AST::class, 'toArray'], $this->types),
            // @phpstan-ignore-next-line Before serialization, those are arrays
            self::DIRECTIVES => array_map([AST::class, 'toArray'], $this->directives),
            self::CLASS_NAME_TO_OBJECT_TYPE_NAME => $this->classNameToObjectTypeNames,
        ];
    }

    /**
     * Instantiate from a serialized array.
     *
     * @param  SerializableDocumentAST  $ast
     */
    public static function fromArray(array $ast): DocumentAST
    {
        $documentAST = new static();
        $documentAST->hydrateFromArray($ast);

        return $documentAST;
    }

    /**
     * @return SerializableDocumentAST
     */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * @deprecated TODO remove in v6
     */
    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    /**
     * @param  SerializableDocumentAST  $data
     */
    public function __unserialize(array $data): void
    {
        $this->hydrateFromArray($data);
    }

    /**
     * @deprecated TODO remove in v6
     */
    public function unserialize($data): void
    {
        $this->__unserialize(unserialize($data));
    }

    /**
     * @param  SerializableDocumentAST  $ast
     */
    protected function hydrateFromArray(array $ast): void
    {
        [
            self::TYPES => $types,
            self::DIRECTIVES => $directives,
            self::CLASS_NAME_TO_OBJECT_TYPE_NAME => $this->classNameToObjectTypeNames,
        ] = $ast;

        // Utilize the NodeList for lazy unserialization for performance gains.
        // Until they are accessed by name, they are kept in their array form.

        // @phpstan-ignore-next-line Since we start from the array form, the generic type does not match
        $this->types = new NodeList($types);
        // @phpstan-ignore-next-line Since we start from the array form, the generic type does not match
        $this->directives = new NodeList($directives);
    }
}

<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Cache\CacheKeyDirective;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\RootType;

class TypeValue
{
    /** Cache key for this type. */
    protected string $cacheKey;

    public function __construct(
        protected TypeDefinitionNode $typeDefinition,
    ) {}

    /** Get the name of the node. */
    public function getTypeDefinitionName(): string
    {
        return $this->getTypeDefinition()->getName()->value;
    }

    /** Get the underlying type definition. */
    public function getTypeDefinition(): TypeDefinitionNode
    {
        return $this->typeDefinition;
    }

    public function cacheKey(): ?string
    {
        if (! isset($this->cacheKey)) {
            $typeName = $this->getTypeDefinitionName();

            // The Query type is exempt from requiring a cache key
            if ($typeName === RootType::QUERY) {
                return null;
            }

            $typeDefinition = $this->typeDefinition;
            if (! $typeDefinition instanceof ObjectTypeDefinitionNode) {
                $expected = ObjectTypeDefinitionNode::class;
                $actual = $typeDefinition::class;
                throw new DefinitionException("Can only determine cacheKey for types of {$expected}, but type {$typeName} is {$actual}.");
            }

            $fieldDefinitions = $typeDefinition->fields;

            // First priority: Look for a field with the @cacheKey directive
            foreach ($fieldDefinitions as $field) {
                if (ASTHelper::hasDirective($field, CacheKeyDirective::NAME)) {
                    return $this->cacheKey = ASTHelper::internalFieldName($field);
                }
            }

            // Second priority: Look for a Non-Null field with the ID type
            foreach ($fieldDefinitions as $field) {
                $fieldType = $field->type;

                if (
                    $fieldType instanceof NonNullTypeNode
                    && $fieldType->type instanceof NamedTypeNode
                    && $fieldType->type->name->value === 'ID'
                ) {
                    return $this->cacheKey = ASTHelper::internalFieldName($field);
                }
            }

            throw new DefinitionException("No @cacheKey or ID field defined on {$typeName}");
        }

        return $this->cacheKey;
    }
}

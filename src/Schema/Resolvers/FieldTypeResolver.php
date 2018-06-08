<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

class FieldTypeResolver
{
    /**
     * Parse type from node.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    public static function resolve(FieldValue $value)
    {
        return $value->setType(
            (new static())->resolveNodeType($value->getField())
        );
    }

    /**
     * Parse type from input.
     *
     * @param InputValueDefinitionNode $input
     *
     * @return Type
     */
    public static function resolveInput(InputValueDefinitionNode $input)
    {
        return (new static())->resolveNodeType($input);
    }

    /**
     * Unpack type.
     *
     * @param mixed $type
     *
     * @return Type
     */
    public static function unpack($type)
    {
        return (new static())->unpackNodeType($type);
    }

    /**
     * Get type from node.
     *
     * @param mixed $node
     * @param array $wrappers
     *
     * @return mixed
     */
    public function resolveNodeType($node, array $wrappers = [])
    {
        if ('NonNullType' == $node->kind) {
            return $this->resolveNodeType(
                $node->type,
                array_merge($wrappers, ['NonNullType'])
            );
        } elseif ('ListType' == $node->kind) {
            return $this->resolveNodeType(
                $node->type,
                array_merge($wrappers, ['ListType'])
            );
        }

        return collect($wrappers)
            ->reverse()
            ->reduce(function ($type, $kind) {
                if ('NonNullType' == $kind) {
                    return Type::nonNull($type);
                } elseif ('ListType' == $kind) {
                    return Type::listOf($type);
                }

                return $type;
            }, $this->extractTypeFromNode($node));
    }

    /**
     * Unpack and resolve node type.
     *
     * @param mixed $type
     * @param array $wrappers
     *
     * @return Type
     */
    public function unpackNodeType($type, array $wrappers = [])
    {
        $type = is_callable($type) ? $type() : $type;

        if ($type instanceof ListOfType) {
            return $this->unpackNodeType($type->getWrappedType(), array_merge($wrappers, ['ListOfType']));
        } elseif ($type instanceof NonNull) {
            return $this->unpackNodeType($type->getWrappedType(), array_merge($wrappers, ['NonNull']));
        }

        return collect($wrappers)
            ->reverse()
            ->reduce(function ($innerType, $wrapper) {
                return 'ListOfType' === $wrapper
                    ? Type::listOf($innerType)
                    : Type::nonNull($innerType);
            }, $type);
    }

    /**
     * Extract type from node definition.
     *
     * @param mixed $node
     *
     * @return mixed
     */
    protected function extractTypeFromNode($node)
    {
        if ($node instanceof NamedTypeNode) {
            return $this->convertNamedType($node);
        }
    }

    /**
     * Converted named node to type.
     *
     * @param NamedTypeNode $node
     *
     * @return mixed
     */
    protected function convertNamedType(NamedTypeNode $node)
    {
        switch ($node->name->value) {
            case 'ID':
                return Type::id();
            case 'Int':
                return Type::int();
            case 'Boolean':
                return Type::boolean();
            case 'Float':
                return Type::float();
            case 'String':
                return Type::string();
            default:
                return $this->convertCustomType($node->name->value);
        }
    }

    /**
     * Convert custom node type.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function convertCustomType($name)
    {
        return function () use ($name) {
            return types()->get($name);
        };
    }
}

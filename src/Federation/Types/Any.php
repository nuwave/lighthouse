<?php

namespace Nuwave\Lighthouse\Federation\Types;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\AST;
use Nuwave\Lighthouse\Federation\EntityResolverProvider;

/**
 * @see \MLL\GraphQLScalars\MixedScalar
 */
class Any extends ScalarType
{
    const MESSAGE = 'Expected an input with a field `__typename` and matching fields, got: ';

    public $name = '_Any';

    public $description = /** @lang Markdown */ <<<'DESCRIPTION'
Representation of entities from external services for the root `_entities` field.
DESCRIPTION;

    public function serialize($value)
    {
        return $value;
    }

    /**
     * @return array{__typename: string}
     */
    public function parseValue($value): array
    {
        // We do as much validation as possible here, before entering resolvers

        if (! is_array($value)) {
            throw new Error(self::MESSAGE.\Safe\json_encode($value));
        }

        $typename = $value['__typename'] ?? null;
        if (! is_string($typename)) {
            throw new Error(self::MESSAGE.\Safe\json_encode($value));
        }

        /** @var \Nuwave\Lighthouse\Federation\EntityResolverProvider $entityResolverProvider */
        $entityResolverProvider = app(EntityResolverProvider::class);

        // Representations must contain at least the fields defined in the fieldset of a @key directive on the base type.
        $definition = $entityResolverProvider->typeDefinition($typename);
        $keyFieldsSelections = $entityResolverProvider->keyFieldsSelections($definition);
        $entityResolverProvider->firstSatisfiedKeyFields($keyFieldsSelections, $value);

        // Ensure we actually have a resolver for the type available
        $entityResolverProvider->resolver($typename);

        // @phpstan-ignore-next-line type inference is too weak
        return $value;
    }

    /**
     * @return array{__typename: string}
     */
    public function parseLiteral($valueNode, ?array $variables = null): array
    {
        return $this->parseValue(
            AST::valueFromASTUntyped($valueNode)
        );
    }
}

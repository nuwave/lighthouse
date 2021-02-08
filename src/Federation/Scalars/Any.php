<?php

namespace Nuwave\Lighthouse\Federation\Scalars;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\AST;

/**
 * @see \MLL\GraphQLScalars\MixedScalar
 */
class Any extends ScalarType
{
    const MESSAGE = 'Expected an input with a field `__typename` and matching fields, got: ';

    public $name = '_Any';

    public $description = <<<'DESCRIPTION'
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
        if (! is_array($value)) {
            throw new Error(self::MESSAGE.\Safe\json_encode($value));
        }

        if (! isset($value['__typename'])) {
            throw new Error(self::MESSAGE.\Safe\json_encode($value));
        }

        // TODO validate fields match the @external fields of the __typename

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

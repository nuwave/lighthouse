<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use Illuminate\Http\UploadedFile;

class Upload extends ScalarType
{
    /**
     * This always throws, as the Upload scalar can only be used as an argument.
     *
     * @throws \GraphQL\Error\InvariantViolation
     */
    public function serialize($value): void
    {
        throw new InvariantViolation(
            '"Upload" cannot be serialized, it can only be used as an argument.'
        );
    }

    /**
     * Parse a externally provided variable value into a Carbon instance.
     *
     * @throws \GraphQL\Error\Error
     */
    public function parseValue($value): UploadedFile
    {
        if (! $value instanceof UploadedFile) {
            throw new Error(
                'Could not get uploaded file, be sure to conform to GraphQL multipart request specification: https://github.com/jaydenseric/graphql-multipart-request-spec Instead got: ' . Utils::printSafe($value)
            );
        }

        return $value;
    }

    /**
     * This always throws, as the Upload scalar must be used with a multipart form request.
     *
     * @param  \GraphQL\Language\AST\Node  $valueNode
     * @param  array<string, mixed>|null  $variables
     *
     * @throws \GraphQL\Error\Error
     */
    public function parseLiteral($valueNode, array $variables = null): void
    {
        throw new Error(
            '"Upload" cannot be hardcoded in a query. Be sure to conform to the GraphQL multipart request specification: https://github.com/jaydenseric/graphql-multipart-request-spec'
        );
    }
}

<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use GraphQL\Error\Error;
use GraphQL\Utils\Utils;
use Illuminate\Http\UploadedFile;
use GraphQL\Type\Definition\ScalarType;

class Upload extends ScalarType
{
    public $name = 'Upload';

    public $description = ''; // TODO Add description / refer to docs.

    /**
     * Serialize an internal value, ensuring it is a valid date string.
     *
     * @param $value
     * @throws Error
     */
    public function serialize($value)
    {
        throw new Error('"Upload" cannot be serialized');
    }

    /**
     * Parse a externally provided variable value into a Carbon instance.
     */
    public function parseValue($value): UploadedFile
    {
        if (! $value instanceof UploadedFile) {
            throw new Error('Could not get uploaded file, be sure to conform to GraphQL multipart request specification. Instead got: '.Utils::printSafe($value));
        }

        return $value;
    }

    /**
     * Parse a literal provided as part of a GraphQL query string into a Carbon instance.
     *
     * @param  \GraphQL\Language\AST\Node  $valueNode
     * @param  mixed[]|null  $variables
     *
     * @throws \GraphQL\Error\Error
     */
    public function parseLiteral($valueNode, array $variables = null)
    {
        throw new Error('"Upload" cannot be hardcoded in query. Be sure to conform to GraphQL multipart request specification.');
    }
}

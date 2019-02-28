<?php

namespace Tests\Utils\Mutations;
use Exception;
use Illuminate\Http\UploadedFile;

/**
 * This is used solely as a placeholder resolver, as schemas without a valid
 * field in the query type are invalid.
 */
class Upload
{
    /**
     * Return a value for the field.
     *
     * @return array
     */
    public function resolve($root, $args): array
    {
        if(! $args['file'] instanceof UploadedFile) {
            throw new Exception('Argument "file" is not of type "UploadedFile"');
        }

        return [
            'id' => 123,
            'url' => 'http://localhost.dev/image_123.jpg',
        ];
    }
}

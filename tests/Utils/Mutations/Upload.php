<?php

namespace Tests\Utils\Mutations;

use Exception;
use Illuminate\Http\UploadedFile;

class Upload
{
    /**
     * Return a value for the field.
     *
     * @return array
     * @throws Exception
     */
    public function resolve($root, $args): array
    {
        if (! $args['file'] instanceof UploadedFile) {
            throw new Exception('Argument "file" is not of type "UploadedFile"');
        }

        return [
            'id' => 123,
            'url' => 'http://localhost.dev/image_123.jpg',
        ];
    }
}

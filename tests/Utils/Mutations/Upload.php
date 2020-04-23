<?php

namespace Tests\Utils\Mutations;

use Exception;
use Illuminate\Http\UploadedFile;

class Upload
{
    /**
     * Return a value for the field.
     *
     * @param  mixed[]  $args
     *
     * @throws Exception
     */
    public function resolve($root, array $args): bool
    {
        return $args['file'] instanceof UploadedFile;
    }
}

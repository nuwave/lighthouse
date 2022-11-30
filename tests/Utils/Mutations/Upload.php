<?php

namespace Tests\Utils\Mutations;

use Illuminate\Http\UploadedFile;

final class Upload
{
    /**
     * @param  array<string, mixed> $args
     */
    public function __invoke($root, array $args): bool
    {
        return isset($args['file'])
            && $args['file'] instanceof UploadedFile;
    }
}

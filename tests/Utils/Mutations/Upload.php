<?php declare(strict_types=1);

namespace Tests\Utils\Mutations;

use Illuminate\Http\UploadedFile;

final class Upload
{
    /** @param  array<string, mixed>  $args */
    public function __invoke(mixed $root, array $args): bool
    {
        return isset($args['file'])
            && $args['file'] instanceof UploadedFile;
    }
}

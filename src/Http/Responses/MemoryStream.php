<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Http\Responses;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;

class MemoryStream extends Stream implements CanStreamResponse
{
    /** @var array<int, mixed> */
    public array $chunks = [];

    public function stream(array $data, array $paths, bool $isFinalChunk): void
    {
        if ($paths === []) {
            $this->chunks[] = $data;
        } else {
            $chunk = [];
            foreach ($paths as $path) {
                $response = ['data' => Arr::get($data, "data.{$path}", [])];

                $errors = $this->chunkError($path, $data);
                if ($errors !== null && $errors !== []) {
                    $response['errors'] = $errors;
                }

                $chunk[$path] = $response;
            }

            $this->chunks[] = $chunk;
        }
    }
}

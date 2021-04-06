<?php

namespace Nuwave\Lighthouse\Support\Http\Responses;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;

class MemoryStream extends Stream implements CanStreamResponse
{
    /**
     * @var array<int, mixed>
     */
    public $chunks = [];

    public function stream(array $data, array $paths, bool $isFinalChunk): void
    {
        if (! empty($paths)) {
            foreach ($paths as $path) {
                $response = ['data' => Arr::get($data, "data.{$path}", [])];

                $errors = $this->chunkError($path, $data);
                if (! empty($errors)) {
                    $response['errors'] = $errors;
                }

                $data[$path] = $response;
            }
        }

        $this->chunks[] = $data;
    }
}

<?php

namespace Nuwave\Lighthouse\Support\Http\Responses;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;

class MemoryStream extends Stream implements CanStreamResponse
{
    /** @var bool */
    public $chunks = [];

    /**
     * Stream graphql response.
     *
     * @param array $data
     * @param array $paths
     * @param bool  $final
     */
    public function stream(array $data, array $paths = [], bool $final)
    {
        if (! empty($paths)) {
            $data = collect($paths)->mapWithKeys(function ($path) use ($data) {
                $response['data'] = Arr::get($data, "data.{$path}", []);
                $errors = $this->chunkError($path, $data);
                if (! empty($errors)) {
                    $response['errors'] = $errors;
                }

                return [$path => $response];
            })->toArray();
        }

        $this->chunks[] = $data;
    }
}

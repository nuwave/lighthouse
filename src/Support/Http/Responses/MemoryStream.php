<?php

namespace Nuwave\Lighthouse\Support\Http\Responses;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;

class MemoryStream extends Stream implements CanStreamResponse
{
    /**
     * @var array<int, mixed>
     */
    public $chunks = [];

    public function stream(array $data, array $paths, bool $final): void
    {
        if (! empty($paths)) {
            $data = (new Collection($paths))
                ->mapWithKeys(
                    /**
                     * @return array<string, array<string, mixed>>
                     */
                    function (string $path) use ($data): array {
                        $response = ['data' => Arr::get($data, "data.{$path}", [])];

                        $errors = $this->chunkError($path, $data);
                        if (! empty($errors)) {
                            $response['errors'] = $errors;
                        }

                        return [$path => $response];
                    }
                )
                ->all();
        }

        $this->chunks[] = $data;
    }
}

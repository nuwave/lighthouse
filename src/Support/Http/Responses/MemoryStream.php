<?php

namespace Nuwave\Lighthouse\Support\Http\Responses;

use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;

class MemoryStream implements CanStreamResponse
{
    /** @var bool */
    public $chunks = [];

    /**
     * Stream graphql response.
     *
     * @param array $data
     * @param array $paths
     * @param bool  $final
     *
     * @return mixed
     */
    public function stream(array $data, array $paths = [], bool $final)
    {
        if (! empty($paths)) {
            $data = collect($paths)->mapWithKeys(function ($path) use ($data) {
                return [$path => array_get($data, "data.{$path}", [])];
            })->toArray();
        }

        $this->chunks[] = $data;
    }
}

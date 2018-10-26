<?php

namespace Nuwave\Lighthouse\Support\Http\Responses;

class Memory implements CanSendResponse
{
    /** @var bool */
    public $chunks = [];

    /**
     * Send response.
     *
     * @param array $data
     * @param array $paths
     *
     * @return mixed
     */
    public function send(array $data, array $paths = [])
    {
        if (! empty($paths)) {
            $data = collect($paths)->mapWithKeys(function ($path) use ($data) {
                return [$path => array_get($data, "data.{$path}", [])];
            })->toArray();
        }

        $this->chunks[] = $data;
    }
}

<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface CanStreamResponse
{
    /**
     * Stream graphql response.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $paths
     *
     * @return void This function is expected to emit a stream as a side effect
     */
    public function stream(array $data, array $paths, bool $isFinalChunk);
}

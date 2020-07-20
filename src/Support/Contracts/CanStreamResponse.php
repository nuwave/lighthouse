<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface CanStreamResponse
{
    /**
     * Stream graphql response.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $paths
     * @param  bool  $final  Indicates this is the last chunk to be sent
     * @return void  This function emits through a side effect
     */
    public function stream(array $data, array $paths, bool $final);
}

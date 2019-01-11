<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface CanStreamResponse
{
    /**
     * Stream graphql response.
     *
     * @param  array  $data
     * @param  array  $paths
     * @param  bool  $final
     * @return mixed
     */
    public function stream(array $data, array $paths, bool $final);
}

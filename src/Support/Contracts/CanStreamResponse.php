<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface CanStreamResponse
{
    /**
     * Stream graphql response.
     */
    public function stream(array $data, array $paths, bool $final);
}

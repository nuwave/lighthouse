<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface CreatesResponse
{
    /**
     * Create a HTTP response from the final result.
     *
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $result
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createResponse(array $result);
}

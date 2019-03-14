<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface CreatesResponse
{
    /**
     * Create a HTTP response from the final result.
     *
     * @param  mixed[]  $result
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createResponse(array $result);
}

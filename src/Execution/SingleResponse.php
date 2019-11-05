<?php

namespace Nuwave\Lighthouse\Execution;

use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Symfony\Component\HttpFoundation\Response;

class SingleResponse implements CreatesResponse
{
    /**
     * Create a HTTP response from the final result.
     *
     * @param  mixed[]  $result
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createResponse(array $result): Response
    {
        return response($result);
    }
}

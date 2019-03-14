<?php

namespace Nuwave\Lighthouse\Execution;

use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;

class SingleResponse implements CreatesResponse
{
    /**
     * Create a HTTP response from the final result.
     *
     * @param  mixed[]  $result
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createResponse(array $result)
    {
        return response($result);
    }
}

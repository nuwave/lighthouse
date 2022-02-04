<?php

namespace Nuwave\Lighthouse\Execution;

use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Symfony\Component\HttpFoundation\Response;

class SingleResponse implements CreatesResponse
{
    public function createResponse(array $result): Response
    {
        return response($result);
    }
}

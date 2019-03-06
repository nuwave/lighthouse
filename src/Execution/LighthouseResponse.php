<?php

namespace Nuwave\Lighthouse\Execution;

use Nuwave\Lighthouse\Support\Contracts\GraphQLResponse;

class LighthouseResponse implements GraphQLResponse
{
    /**
     * Create GraphQL response.
     *
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function create(array $data)
    {
        return response($data);
    }
}

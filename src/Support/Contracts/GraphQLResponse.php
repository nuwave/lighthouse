<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface GraphQLResponse
{
    /**
     * Create GraphQL response.
     *
     * @param array $data
     *
     * @return \Illuminate\Http\Response
     */
    public function create(array $data);
}

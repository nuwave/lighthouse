<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface GraphQLResponse
{
    /**
     * Create GraphQL response.
     *
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function create(array $data);
}

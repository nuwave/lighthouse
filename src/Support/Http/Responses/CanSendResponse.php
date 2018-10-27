<?php

namespace Nuwave\Lighthouse\Support\Http\Responses;

interface CanSendResponse
{
    /**
     * Send response.
     *
     * @param array $data
     * @param array $paths
     * @param bool  $final
     *
     * @return mixed
     */
    public function send(array $data, array $paths = [], bool $final);
}

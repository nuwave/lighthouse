<?php

namespace Nuwave\Lighthouse\Support\Interfaces;

interface ConnectionEdge
{
    /**
     * Name of edge.
     *
     * @return string
     */
    public function name();

    /**
     * Edge type.
     *
     * @return string
     */
    public function type();

    /**
     * Resolve cursor.
     *
     * @param  mixed $payload
     * @return mixed
     */
    public function cursor($payload);
}

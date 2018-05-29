<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface GlobalIdResolver extends Directive
{
    /**
     * Resolve node by global id.
     *
     * @param mixed  $id
     * @param string $globalId
     *
     * @return mixed
     */
    public function resolve($id, $globalId); // TODO: Switch this out w/ a NodeValue
}

<?php


namespace Nuwave\Lighthouse\Support\Contracts;


interface Resolver
{
    /**
     * Name of the resolver.
     *
     * @return string
     */
    public function name();

    public function resolve($value);
}
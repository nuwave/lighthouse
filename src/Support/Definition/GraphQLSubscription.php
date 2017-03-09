<?php

namespace Nuwave\Lighthouse\Support\Definition;

abstract class GraphQLSubscription extends GraphQLQuery
{
    /**
     * Authorize subscription and create context.
     *
     * @param  array $args
     * @param  array $params
     * @return mixed
     */
    abstract public function onSubscribe(array $args, array $params = []);
}

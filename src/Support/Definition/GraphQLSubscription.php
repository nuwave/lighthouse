<?php

namespace Nuwave\Lighthouse\Support\Definition;

abstract class GraphQLSubscription extends GraphQLQuery
{
    /**
     * Determine if request is authorized to subscribe.
     *
     * @param  array $args
     * @param  mixed $request
     * @param  \Illuminate\Support\Collection $context
     * @return boolean
     */
    abstract public function canSubscribe(array $args, $request, $context);

    /**
     * Filter subscription.
     *
     * @param  array $args
     * @param  \Illuminate\Support\Collection $context
     * @return boolean
     */
    abstract public function filter(array $args, $context);
}

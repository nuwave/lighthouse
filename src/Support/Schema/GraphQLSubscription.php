<?php

namespace Nuwave\Lighthouse\Support\Schema;

use Illuminate\Broadcasting\Channel;

abstract class GraphQLSubscription implements ShouldBroadcast
{
    /**
     * Root object.
     *
     * @var mixed
     */
    protected $obj;

    /**
     * Field arguments.
     *
     * @var array
     */
    protected $args = [];

    /**
     * Query context.
     *
     * @var Context
     */
    protected $context;

    /**
     * Field resolve info.
     *
     * @var ResolveInfo
     */
    protected $info;

    /**
     * Resolve the mutation.
     *
     * @param mixed       $obj
     * @param array       $args
     * @param Context     $context
     * @param ResolveInfo $info
     * @return mixed
     */
    public function resolve($obj, $args = null, $context = null, $info = null)
    {
        $this->obj = $obj;
        $this->args = $args;
        $this->context = $context;
        $this->info = $info;
        return $this->resolve();
    }

    /**
     * Resolve the mutation.
     *
     * @return mixed
     */
    abstract public function resolve();

    public function broadcastOn()
    {
        return new Channel('graphql');
    }
}

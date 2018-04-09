<?php

namespace Nuwave\Lighthouse\Support\Schema;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Context;

abstract class GraphQLResolver
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
     * Create new instance of field resolver.
     *
     * @param mixed       $obj
     * @param array       $args
     * @param Context     $context
     * @param ResolveInfo $info
     */
    public function __construct($obj, $args = null, $context = null, $info = null)
    {
        $this->obj = $obj;
        $this->args = $args;
        $this->context = $context;
        $this->info = $info;
    }

    /**
     * Resolve the mutation.
     *
     * @return mixed
     */
    abstract public function resolve();
}

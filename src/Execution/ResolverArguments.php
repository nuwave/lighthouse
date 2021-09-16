<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;

class ResolverArguments
{
    /**
     * The value given by the parent resolver.
     *
     * @var mixed
     */
    public $root;

    /**
     * The arguments given by the client.
     *
     * @var ArgumentSet
     */
    public $args;

    /**
     * Additional information about the execution status.
     *
     * @var ResolveInfo
     */
    public $info;
}

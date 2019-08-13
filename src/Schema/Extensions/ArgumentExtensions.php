<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

class ArgumentExtensions
{
    /**
     * @var \Closure|null
     */
    public $resolveBefore;
    /**
     * @var \Closure|null
     */
    public $resolveAfter;
}

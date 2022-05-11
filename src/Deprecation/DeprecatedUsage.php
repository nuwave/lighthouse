<?php

namespace Nuwave\Lighthouse\Deprecation;

class DeprecatedUsage
{
    /**
     * How often was the element used?
     *
     * @var int
     */
    public $count;

    /**
     * Why is the element deprecated?
     *
     * @var string
     */
    public $reason;

    public function __construct(string $reason)
    {
        $this->reason = $reason;
    }
}

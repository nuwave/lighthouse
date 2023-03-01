<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Deprecation;

class DeprecatedUsage
{
    /**
     * How often was the element used?
     */
    public int $count;

    /**
     * Why is the element deprecated?
     */
    public string $reason;

    public function __construct(string $reason)
    {
        $this->reason = $reason;
    }
}

<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Deprecation;

class DeprecatedUsage
{
    /** How often was the element used? */
    public int $count = 0;

    public function __construct(
        /**
         * Why is the element deprecated?
         */
        public string $reason,
    ) {}
}

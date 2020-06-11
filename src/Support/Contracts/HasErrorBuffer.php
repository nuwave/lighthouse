<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Execution\ErrorBuffer;

interface HasErrorBuffer
{
    /**
     * Get the ErrorBuffer instance.
     */
    public function errorBuffer(): ErrorBuffer;

    /**
     * Set the ErrorBuffer instance.
     *
     * @return $this
     */
    public function setErrorBuffer(ErrorBuffer $errorBuffer): self;
}

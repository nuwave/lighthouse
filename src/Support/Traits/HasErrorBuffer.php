<?php

namespace Nuwave\Lighthouse\Support\Traits;

use Nuwave\Lighthouse\Execution\ErrorBuffer;

trait HasErrorBuffer
{
    /**
     * @var \Nuwave\Lighthouse\Execution\ErrorBuffer
     */
    protected $errorBuffer;

    /**
     * Get the ErrorBuffer instance.
     */
    public function errorBuffer(): ErrorBuffer
    {
        return $this->errorBuffer;
    }

    /**
     * Set the ErrorBuffer instance.
     *
     * @return $this
     */
    public function setErrorBuffer(ErrorBuffer $errorBuffer): self
    {
        $this->errorBuffer = $errorBuffer;

        return $this;
    }
}

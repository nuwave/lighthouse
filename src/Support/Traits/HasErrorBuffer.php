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
     *
     * @return \Nuwave\Lighthouse\Execution\ErrorBuffer
     */
    public function errorBuffer(): ErrorBuffer
    {
        return $this->errorBuffer;
    }

    /**
     * Set the ErrorBuffer instance.
     *
     * @param  \Nuwave\Lighthouse\Execution\ErrorBuffer  $errorBuffer
     * @return $this
     */
    public function setErrorBuffer(ErrorBuffer $errorBuffer): self
    {
        $this->errorBuffer = $errorBuffer;

        return $this;
    }
}

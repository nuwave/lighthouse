<?php

namespace Nuwave\Lighthouse\Support\Traits;

trait HasArgumentValue
{
    /**
     * @var \Nuwave\Lighthouse\Execution\Arguments\Argument|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    protected $argumentValue;

    public function argumentValue()
    {
        return $this->argumentValue;
    }

    /**
     * @return $this
     */
    public function setArgumentValue($argument): self
    {
        $this->argumentValue = $argument;

        return $this;
    }
}

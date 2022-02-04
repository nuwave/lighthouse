<?php

namespace Nuwave\Lighthouse\Support\Traits;

trait HasArgumentValue
{
    /**
     * @var \Nuwave\Lighthouse\Execution\Arguments\Argument|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    protected $argumentValue;

    /**
     * @return \Nuwave\Lighthouse\Execution\Arguments\Argument|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    public function argumentValue()
    {
        return $this->argumentValue;
    }

    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\Argument|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $argument
     */
    public function setArgumentValue($argument): self
    {
        $this->argumentValue = $argument;

        return $this;
    }
}

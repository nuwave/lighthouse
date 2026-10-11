<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Traits;

use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;

trait HasArgumentValue
{
    protected Argument|ArgumentSet $argumentValue;

    public function argumentValue(): Argument|ArgumentSet
    {
        return $this->argumentValue;
    }

    public function setArgumentValue(Argument|ArgumentSet $argument): self
    {
        $this->argumentValue = $argument;

        return $this;
    }
}

<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\PreSaveArgumentsAware;

/**
 * Pass pre-save arguments on to the wrapped resolver, which is the one that saves.
 */
trait DelegatesPreSaveArguments
{
    /** @param  list<\Nuwave\Lighthouse\Execution\Arguments\Argument>  $arguments */
    public function withPreSaveArguments(array $arguments): ?static
    {
        $previous = $this->previous;
        if (! $previous instanceof PreSaveArgumentsAware) {
            return null;
        }

        $previousWithPreSave = $previous->withPreSaveArguments($arguments);
        if ($previousWithPreSave === null) {
            return null;
        }

        $clone = clone $this;
        $clone->previous = $previousWithPreSave;

        return $clone;
    }
}

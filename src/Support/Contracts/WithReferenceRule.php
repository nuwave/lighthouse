<?php

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * Used for custom validation classes with references to other arguments.
 */
interface WithReferenceRule
{
    /**
     * Called with the argument path leading up to this argument, before validation runs.
     *
     * @param  array<int|string>  $argumentPath
     */
    public function setArgumentPath(array $argumentPath): void;
}

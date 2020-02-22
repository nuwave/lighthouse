<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface HasArgumentPath
{
    /**
     * Get all the resolver arguments.
     *
     * @return array
     */
    public function argumentValue(): array;

    /**
     * @param  array  $argumentPath
     * @return static
     */
    public function setArgumentValue(array $argumentPath);
}

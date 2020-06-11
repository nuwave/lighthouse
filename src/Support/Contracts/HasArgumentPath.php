<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface HasArgumentPath
{
    /**
     * Get all the resolver arguments.
     *
     * @return array<string|int>
     */
    public function argumentPath(): array;

    /**
     * Set the path to the argument from the root of the field.
     *
     * @param  array<string|int>  $argumentPath
     * @return static
     */
    public function setArgumentPath(array $argumentPath);
}

<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface HasArgumentPath
{
    /**
     * Get all the resolver arguments.
     *
     * @return array
     */
    public function argumentPath(): array;

    /**
     * @param  array  $argumentPath
     * @return static
     */
    public function setArgumentPath(array $argumentPath);
}

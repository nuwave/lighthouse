<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface HasArgumentPath
{
    /**
     * Get all the resolver arguments.
     */
    public function argumentPath(): array;

    /**
     * @return static
     */
    public function setArgumentPath(array $argumentPath);
}

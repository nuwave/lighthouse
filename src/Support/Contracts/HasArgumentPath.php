<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface HasArgumentPath
{
    /**
     * Get all the resolver arguments.
     *
     * @return string
     */
    public function argumentPath(): string;

    /**
     * @param string $argumentPath
     *
     * @return static
     */
    public function setArgumentPath(string $argumentPath);
}

<?php

namespace Nuwave\Lighthouse\Support\Traits;

trait HasArgumentPath
{
    /**
     * @var string
     */
    protected $argumentPath;

    /**
     * @return string
     */
    public function argumentPath(): string
    {
        return $this->argumentPath;
    }

    /**
     * @param mixed $argumentPath
     *
     * @return static
     */
    public function setArgumentPath(string  $argumentPath)
    {
        $this->argumentPath = $argumentPath;

        return $this;
    }
}

<?php

namespace Nuwave\Lighthouse\Support\Traits;

trait HasArgumentPath
{
    /**
     * @var array
     */
    protected $argumentPath;

    /**
     * @return array
     */
    public function argumentPath(): array
    {
        return $this->argumentPath;
    }

    /**
     * @return string
     */
    public function argumentPathAsDotNotation(): string
    {
        return implode('.', $this->argumentPath);
    }

    /**
     * @param array $argumentPath
     *
     * @return static
     */
    public function setArgumentPath(array $argumentPath)
    {
        $this->argumentPath = $argumentPath;

        return $this;
    }
}

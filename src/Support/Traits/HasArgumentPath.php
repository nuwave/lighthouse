<?php

namespace Nuwave\Lighthouse\Support\Traits;

trait HasArgumentPath
{
    /**
     * @var mixed[]
     */
    protected $argumentPath;

    /**
     * @return mixed[]
     */
    public function argumentPath(): array
    {
        return $this->argumentPath;
    }

    public function argumentPathAsDotNotation(): string
    {
        return implode('.', $this->argumentPath);
    }

    /**
     * @param  mixed[]  $argumentPath
     * @return $this
     */
    public function setArgumentPath(array $argumentPath): self
    {
        $this->argumentPath = $argumentPath;

        return $this;
    }
}

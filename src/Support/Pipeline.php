<?php

namespace Nuwave\Lighthouse\Support;

use Illuminate\Support\Collection;
use Illuminate\Pipeline\Pipeline as BasePipeline;

class Pipeline extends BasePipeline
{
    protected $always = null;

    /**
     * Set the array of pipes.
     *
     * @param Collection|array $pipes
     *
     * @return self
     */
    public function through($pipes)
    {
        if ($pipes instanceof Collection) {
            $pipes = $pipes->toArray();
        }

        return parent::through($pipes);
    }

    /**
     * Get a \Closure that represents a slice of the application onion.
     *
     * @return \Closure
     */
    protected function carry()
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                if (! is_null($this->always)) {
                    $passable = ($this->always)($passable, $pipe);
                }
                $slice = parent::carry();

                $callable = $slice($stack, $pipe);

                return $callable($passable);
            };
        };
    }

    /**
     * Set always variable.
     *
     * @param \Closure $always
     *
     * @return self
     */
    public function always(\Closure $always)
    {
        $this->always = $always;

        return $this;
    }
}

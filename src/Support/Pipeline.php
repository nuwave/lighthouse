<?php

namespace Nuwave\Lighthouse\Support;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Pipeline\Pipeline as BasePipeline;

class Pipeline extends BasePipeline
{
    protected $always = null;

    /**
     * Set the array of pipes.
     *
     * @param  \Illuminate\Support\Collection|array  $pipes
     * @return $this
     */
    public function through($pipes)
    {
        if ($pipes instanceof Collection) {
            $pipes = $pipes->all();
        }

        return parent::through($pipes);
    }

    /**
     * Get a \Closure that represents a slice of the application onion.
     *
     * @return \Closure
     */
    protected function carry(): Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                if ($this->always !== null) {
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
     * @param  \Closure  $always
     * @return $this
     */
    public function always(Closure $always): self
    {
        $this->always = $always;

        return $this;
    }
}

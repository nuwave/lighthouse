<?php

namespace Nuwave\Lighthouse\Support;

use Closure;
use Illuminate\Pipeline\Pipeline as BasePipeline;
use Illuminate\Support\Collection;

class Pipeline extends BasePipeline
{
    /**
     * @var \Closure|null
     */
    protected $always = null;

    /**
     * Set the array of pipes.
     *
     * @param  \Illuminate\Support\Collection<mixed>|array<mixed>  $pipes
     * @return $this
     */
    public function through($pipes): self
    {
        if ($pipes instanceof Collection) {
            $pipes = $pipes->all();
        }

        return parent::through($pipes);
    }

    /**
     * Get a \Closure that represents a slice of the application onion.
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
     * @return $this
     */
    public function always(Closure $always): self
    {
        $this->always = $always;

        return $this;
    }
}

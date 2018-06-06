<?php


namespace Nuwave\Lighthouse\Schema;


use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Types\Argument;
use Nuwave\Lighthouse\Types\HasAttributes;

class Directive
{
    use HasAttributes;

    protected $name;

    protected $arguments;

    /**
     * Directive constructor.
     *
     * @param $name
     * @param $arguments
     */
    public function __construct(string $name, Collection $arguments)
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }

    public function name() : string
    {
        return $this->name;
    }

    public function arguments() : Collection
    {
        return $this->arguments;
    }

    public function argument($name) : ?Argument
    {
        return $this->arguments()->first(function (Argument $argument) use ($name) {
            return $argument->name() === $name;
        });
    }
}

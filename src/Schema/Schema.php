<?php


namespace Nuwave\Lighthouse\Schema;


use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Types\Type;

class Schema
{
    protected $types;

    /**
     * Schema constructor.
     *
     * @param $types
     */
    public function __construct($types)
    {
        $this->types = $types;
    }


    public function types() : Collection
    {
        return $this->types;
    }

    public function type($name) : ?Type
    {
        return $this->types()->firstWhere('name', $name);
    }
}
<?php


namespace Nuwave\Lighthouse\Schema\Traits;


trait HasName
{
    public $name;

    public function name() : string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
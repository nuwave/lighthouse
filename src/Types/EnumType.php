<?php


namespace Nuwave\Lighthouse\Types;


use Illuminate\Support\Collection;

class EnumType extends Type
{
    protected $values;

    /**
     * EnumType constructor.
     *
     * @param string $name
     * @param string $description
     * @param Collection $values
     */
    public function __construct(string $name, string $description, Collection $values)
    {
        parent::__construct($name, $description);
        $this->values = $values;
    }

    /**
     * @return Collection
     */
    public function values(): Collection
    {
        return $this->values;
    }

    public function value(string $name) : ?EnumValueType
    {
        return $this->values()->firstWhere('name', $name);
    }
}

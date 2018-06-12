<?php


namespace Nuwave\Lighthouse\Types;


use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;

class EnumType extends Type
{
    protected $values;

    /**
     * EnumType constructor.
     *
     * @param DirectiveRegistry $directiveRegistry
     * @param string $name
     * @param string $description
     * @param Collection $values
     */
    public function __construct(DirectiveRegistry $directiveRegistry, string $name, string $description, Collection $values)
    {
        parent::__construct($directiveRegistry, $name, $description);
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

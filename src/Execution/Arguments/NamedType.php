<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

class NamedType
{
    /**
     * The name of the type as defined in the schema.
     *
     * @var string
     */
    public $name;

    /**
     * Is this type defined to be non-nullable?
     *
     * @var bool
     */
    public $nonNull = false;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

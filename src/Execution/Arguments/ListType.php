<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

class ListType
{
    /**
     * The type contained within the list.
     *
     * @var \Nuwave\Lighthouse\Execution\Arguments\NamedType
     */
    public $type;

    /**
     * Is the list itself defined to be non-nullable?
     *
     * @var bool
     */
    public $nonNull = false;

    public function __construct(NamedType $type)
    {
        $this->type = $type;
    }
}

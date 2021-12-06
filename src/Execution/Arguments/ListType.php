<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

class ListType
{
    /**
     * The type contained within the list.
     *
     * @var \Nuwave\Lighthouse\Execution\Arguments\NamedType|\Nuwave\Lighthouse\Execution\Arguments\ListType
     */
    public $type;

    /**
     * Is the list itself defined to be non-nullable?
     *
     * @var bool
     */
    public $nonNull = false;

    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\NamedType|\Nuwave\Lighthouse\Execution\Arguments\ListType  $type
     */
    public function __construct($type)
    {
        $this->type = $type;
    }
}

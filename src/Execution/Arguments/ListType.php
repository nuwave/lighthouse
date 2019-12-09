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

    /**
     * ListType constructor.
     *
     * @param  \Nuwave\Lighthouse\Execution\Arguments\NamedType  $type
     * @return void
     */
    public function __construct(NamedType $type)
    {
        $this->type = $type;
    }
}

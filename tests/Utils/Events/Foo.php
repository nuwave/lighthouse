<?php

namespace Tests\Utils\Events;

class Foo
{
    /**
     * @var mixed
     */
    public $value;

    /**
     * Foo constructor.
     * @param  mixed  $value
     * @return void
     */
    public function __construct($value)
    {
        $this->value = $value;
    }
}

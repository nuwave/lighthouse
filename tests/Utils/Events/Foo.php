<?php

namespace Tests\Utils\Events;

class Foo
{
    public $value;

    /**
     * Foo constructor.
     * @return void
     */
    public function __construct($value)
    {
        $this->value = $value;
    }
}

<?php

namespace Tests\Utils\Mutations;

class Bar
{
    protected $obj;

    protected $args;

    protected $context;

    protected $info;

    public function __construct($obj, $args, $context, $info)
    {
        $this->obj = $obj;
        $this->args = $args;
        $this->context = $context;
        $this->info = $info;
    }

    /**
     * Resolve the mutation.
     *
     * @return mixed
     */
    public function resolve()
    {
        return array_get($this->args, 'baz').' '.array_get($this->args, 'bar');
    }
}

<?php

namespace Tests\Utils\Queries;

class FooInvoke
{
    public function __invoke(): int
    {
        return 42;
    }
}

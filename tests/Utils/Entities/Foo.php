<?php

namespace Tests\Utils\Entities;

class Foo
{
    /**
     * @param  array<string, mixed>  $representation
     * @return array<string, mixed>
     */
    public function __invoke(array $representation): array
    {
        return $representation;
    }
}

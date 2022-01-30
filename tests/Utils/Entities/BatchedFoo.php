<?php

namespace Tests\Utils\Entities;

use Nuwave\Lighthouse\Federation\BatchedEntityResolver;

class BatchedFoo implements BatchedEntityResolver
{
    public function __invoke(array $representations): array
    {
        return $representations;
    }
}

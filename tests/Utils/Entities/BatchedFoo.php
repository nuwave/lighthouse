<?php declare(strict_types=1);

namespace Tests\Utils\Entities;

use Nuwave\Lighthouse\Federation\BatchedEntityResolver;

final class BatchedFoo implements BatchedEntityResolver
{
    public function __invoke(array $representations): iterable
    {
        return $representations;
    }
}

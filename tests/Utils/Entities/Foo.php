<?php declare(strict_types=1);

namespace Tests\Utils\Entities;

final class Foo
{
    /**
     * @param  array<string, mixed>  $representation
     *
     * @return array<string, mixed>
     */
    public function __invoke(array $representation): array
    {
        return $representation;
    }
}

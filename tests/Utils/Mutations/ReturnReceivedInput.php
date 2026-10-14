<?php declare(strict_types=1);

namespace Tests\Utils\Mutations;

final class ReturnReceivedInput
{
    /**
     * @param  array<string, mixed>  $args
     *
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args): array
    {
        return $args;
    }
}

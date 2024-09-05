<?php

declare(strict_types=1);

namespace Tests\Utils\Mutations;

class ReturnReceivedInput
{
    public function __invoke($rootValue, array $args): array
    {
        return $args;
    }
}

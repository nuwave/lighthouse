<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Testing;

interface MockableResolver
{
    public function __invoke(): mixed;
}

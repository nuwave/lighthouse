<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation;

interface BatchedEntityResolver
{
    /**
     * Resolve multiple entities of a single type in one batch.
     *
     * @param  array<string, array<string, mixed>>  $representations
     *
     * @return iterable<string, mixed> must preserve the count and keys of $representations
     */
    public function __invoke(array $representations): iterable;
}

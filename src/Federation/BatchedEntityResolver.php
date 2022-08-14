<?php

namespace Nuwave\Lighthouse\Federation;

interface BatchedEntityResolver
{
    /**
     * Resolve multiple entities of a single type in one batch.
     *
     * @param array<int, array<string, mixed>> $representations
     *
     * @return iterable<mixed> must be the same count as $representations
     */
    public function __invoke(array $representations): iterable;
}

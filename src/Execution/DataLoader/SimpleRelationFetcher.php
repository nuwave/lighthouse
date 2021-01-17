<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class SimpleRelationFetcher implements RelationFetcher
{
    /**
     * @var \Closure
     */
    private $decorateBuilder;

    public function __construct(Closure $decorateBuilder)
    {
        $this->decorateBuilder = $decorateBuilder;
    }

    public function fetch(EloquentCollection $parents, string $relationName): void
    {
        $parents->load([$relationName => $this->decorateBuilder]);
    }
}

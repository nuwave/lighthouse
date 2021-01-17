<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;

interface RelationFetcher
{
    public function fetch(EloquentCollection $parents, string $relationName): void;
}

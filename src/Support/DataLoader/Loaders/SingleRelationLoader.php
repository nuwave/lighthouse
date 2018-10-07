<?php

namespace Nuwave\Lighthouse\Support\DataLoader\Loaders;

use Nuwave\Lighthouse\Execution\QueryFilter;
use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Support\DataLoader\ModelRelationLoader;

class SingleRelationLoader extends BatchLoader
{
    /**
     * @var string
     */
    protected $relationName;
    /**
     * @var array
     */
    protected $resolveArgs;

    public function __construct(string $relationName, array $resolveArgs)
    {
        $this->relationName = $relationName;
        $this->resolveArgs = $resolveArgs;
    }

    /**
     * Resolve keys.
     */
    public function resolve(): array
    {
        $parentModels = $this->getParentModels();
        $relations = [$this->relationName => $this->getRelationConstraints()];
        $modelRelationLoader = new ModelRelationLoader($parentModels, $relations);

        return $modelRelationLoader->loadRelations()->getRelationDictionary($this->relationName);
    }

    /**
     *
     * @return \Closure
     */
    protected function getRelationConstraints(): \Closure
    {
        return function ($query) {
            $query->when(isset($args[QueryFilter::QUERY_FILTER_KEY]), function ($query) {
                return QueryFilter::build($query, $this->resolveArgs);
            });
        };
    }
}

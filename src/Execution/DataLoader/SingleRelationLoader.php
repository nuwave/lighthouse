<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Nuwave\Lighthouse\Execution\QueryFilter;

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
     * Resolve the keys.
     *
     * @return array
     */
    public function resolve(): array
    {
        $parentModels = $this->getParentModels();
        $relations = [$this->relationName => $this->getRelationConstraints()];
        $modelRelationLoader = new ModelRelationFetcher($parentModels, $relations);

        return $modelRelationLoader
            ->loadRelations()
            ->getRelationDictionary($this->relationName);
    }
    
    /**
     * Returns a closure that adds the filters to the query.
     *
     * @return \Closure
     */
    protected function getRelationConstraints(): \Closure
    {
        return function ($query) {
            $query->when(
                isset($args[QueryFilter::QUERY_FILTER_KEY]),
                function ($query) {
                    return QueryFilter::build($query, $this->resolveArgs);
                }
            );
        };
    }
}

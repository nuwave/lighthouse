<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

class RelationCountBatchLoader extends BatchLoader
{
    /**
     * The name of the Eloquent relation to count.
     *
     * @var string
     */
    protected $relationName;

    /**
     * This function is called with the relation query builder and may modify it.
     *
     * @var \Closure
     */
    protected $decorateBuilder;

    /**
     * @param  \Closure  $decorateBuilder
     */
    public function __construct(
        string $relationName,
        // Not using a type-hint to avoid resolving those params through the container
        $decorateBuilder
    ) {
        $this->relationName = $relationName;
        $this->decorateBuilder = $decorateBuilder;
    }

    /**
     * Eager-load the relation count.
     *
     * @return array<\Illuminate\Database\Eloquent\Model>
     */
    public function resolve(): array
    {
        $relation = [$this->relationName => $this->decorateBuilder];

        return RelationFetcher
            ::countedParentModels($this->keys, $relation)
            ->all();
    }
}

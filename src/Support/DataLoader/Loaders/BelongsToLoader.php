<?php

namespace Nuwave\Lighthouse\Support\DataLoader\Loaders;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Database\QueryFilter;
use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;

class BelongsToLoader extends BatchLoader
{
    /**
     * @var string
     */
    protected $relation;
    /**
     * @var array
     */
    protected $resolveArgs;

    public function __construct(string $relation, array $resolveArgs)
    {
        $this->relation = $relation;
        $this->resolveArgs = $resolveArgs;
    }

    /**
     * Resolve keys.
     */
    public function resolve(): array
    {
        $parents = \Illuminate\Database\Eloquent\Collection::make(
            collect($this->keys)->pluck('parent')
        );

        return $parents->load([$this->relation => function ($query) {
            $query->when(isset($args['query.filter']), function ($query) {
                return QueryFilter::build($query, $this->resolveArgs);
            });
        }])->mapWithKeys(function (Model $model) {
            return [$model->getKey() => $model->getRelation($this->relation)];
        })->all();
    }
}

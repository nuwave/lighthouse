<?php

namespace Nuwave\Lighthouse\Support\DataLoader\Loaders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Database\QueryFilter;
use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class HasOneLoader extends BatchLoader
{
    use HandlesGlobalId;

    /**
     * @var string
     */
    protected $relation;
    /**
     * @var array
     */
    protected $resolveArgs;
    /**
     * @var array
     */
    protected $scopes;

    /**
     * @param string $relation
     * @param array $resolveArgs
     * @param array $scopes
     */
    public function __construct(string $relation, array $resolveArgs, array $scopes)
    {
        $this->relation = $relation;
        $this->resolveArgs = $resolveArgs;
        $this->scopes = $scopes;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(): array
    {
        $eagerLoadRelationWithConstraints = [$this->relation => function ($query) {
            foreach ($this->scopes as $scope) {
                call_user_func_array([$query, $scope], [$this->resolveArgs]);
            }

            $query->when(isset($args['query.filter']), function ($q) {
                return QueryFilter::build($q, $this->resolveArgs);
            });
        }];

        /** @var Collection $parents */
        $parents = collect($this->keys)->pluck('parent');
        $parents->fetch($eagerLoadRelationWithConstraints);

        return $parents->mapWithKeys(function (Model $model) {
            return [$model->getKey() => $model->getRelation($this->relation)];
        })->all();
    }
}

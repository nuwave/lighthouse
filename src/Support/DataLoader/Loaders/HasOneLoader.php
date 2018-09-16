<?php

namespace Nuwave\Lighthouse\Support\DataLoader\Loaders;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Database\QueryFilter;
use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;

class HasOneLoader extends BatchLoader
{
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
        return collect($this->keys)
            ->pluck('parent')
            // Using our own Collection macro
            ->fetch([$this->relation =>
                function ($query) {
                    foreach ($this->scopes as $scope) {
                        call_user_func_array([$query, $scope], [$this->resolveArgs]);
                    }

                    $query->when(isset($args['query.filter']), function ($q) {
                        return QueryFilter::build($q, $this->resolveArgs);
                    });
                }
            ])
            ->mapWithKeys(function (Model $model) {
                return [$model->getKey() => $model->getRelation($this->relation)];
            })
            ->all();
    }
}

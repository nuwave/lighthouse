<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class SimpleModelsLoader implements ModelsLoader
{
    public function __construct(
        protected string $relation,
        protected \Closure $decorateBuilder,
    ) {}

    public function load(EloquentCollection $parents): void
    {
        $parents->load([$this->relation => $this->decorateBuilder]);
    }

    public function extract(Model $model): mixed
    {
        // Dot notation may be used to eager load nested relations
        $parts = explode('.', $this->relation);

        // We just return the first level of relations for now.
        // They hold the nested relations in case they are needed.
        $firstRelation = $parts[0];

        return $model->getRelation($firstRelation);
    }
}

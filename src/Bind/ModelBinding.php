<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as IlluminateCollection;

use function is_array;

class ModelBinding
{
    /**
     * @param \Nuwave\Lighthouse\Bind\BindDefinition<\Illuminate\Database\Eloquent\Model> $definition
     */
    public function __invoke(mixed $value, BindDefinition $definition): Model|EloquentCollection|null
    {
        $binding = $definition->class::query()
            ->with($definition->with)
            ->whereIn($definition->column, Arr::wrap($value))
            ->get();

        if (is_array($value)) {
            return $this->modelCollection($binding, IlluminateCollection::make($value), $definition);
        }

        return $this->modelInstance($binding, $value, $definition);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model> $results
     */
    private function modelInstance(EloquentCollection $results, mixed $value, BindDefinition $definition): ?Model
    {
        if ($results->count() > 1) {
            throw new MultipleRecordsFoundException($results->count());
        }

        $model = $results->first();

        if ($definition->optional) {
            return $model;
        }

        if ($model === null) {
            throw BindException::notFound($value, $definition);
        }

        return $model;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model> $results
     * @return \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
     */
    private function modelCollection(
        EloquentCollection $results,
        IlluminateCollection $values,
        BindDefinition $definition,
    ): EloquentCollection {
        if ($results->count() > $values->unique()->count()) {
            throw new MultipleRecordsFoundException($results->count());
        }

        if ($definition->optional) {
            return $results->values();
        }

        $results = $results->keyBy($definition->column);
        $missingResults = new IlluminateCollection();

        foreach ($values as $value) {
            if ($results->has($value)) {
                continue;
            }

            $missingResults->push($value);
        }

        if ($missingResults->isNotEmpty()) {
            throw BindException::missingRecords($missingResults->all(), $definition);
        }

        return $results->values();
    }
}

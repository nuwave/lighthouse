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
     * @param int|string|array<int|string> $value
     * @param \Nuwave\Lighthouse\Bind\BindDefinition<\Illuminate\Database\Eloquent\Model> $definition
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection<string, \Illuminate\Database\Eloquent\Model>|null
     */
    public function __invoke(int|string|array $value, BindDefinition $definition): Model|EloquentCollection|null
    {
        $binding = $definition->class::query()
            ->with($definition->with)
            ->whereIn($definition->column, Arr::wrap($value))
            ->get();

        if (is_array($value)) {
            return $this->modelCollection($binding, IlluminateCollection::make($value), $definition);
        }

        return $this->modelInstance($binding);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $results
     */
    private function modelInstance(EloquentCollection $results): ?Model
    {
        if ($results->count() > 1) {
            throw new MultipleRecordsFoundException($results->count());
        }

        return $results->first();
    }

    /**
     * Binding collections should be returned with the original values
     * as keys to allow us to validate the binding when non-optional.
     * @see \Nuwave\Lighthouse\Bind\BindDirective::rules()
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $results
     * @param \Illuminate\Support\Collection<int, mixed> $values
     * @param \Nuwave\Lighthouse\Bind\BindDefinition<\Illuminate\Database\Eloquent\Model> $definition
     * @return \Illuminate\Database\Eloquent\Collection<string, \Illuminate\Database\Eloquent\Model>
     */
    private function modelCollection(
        EloquentCollection $results,
        IlluminateCollection $values,
        BindDefinition $definition,
    ): EloquentCollection {
        if ($results->count() > $values->unique()->count()) {
            throw new MultipleRecordsFoundException($results->count());
        }

        return $results->keyby($definition->column);
    }
}

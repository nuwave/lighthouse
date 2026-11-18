<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as IlluminateCollection;

class ModelBinding
{
    /**
     * @param  int|string|array<int|string>  $value
     * @param  \Nuwave\Lighthouse\Bind\BindDefinition<\Illuminate\Database\Eloquent\Model>  $definition
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>|null
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

    /** @param  \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>  $results */
    protected function modelInstance(EloquentCollection $results): ?Model
    {
        // Too few records are client-safe validation errors, see the `BindingExists` rule in BindDirective.
        // Too many records mean the binding does not use a unique identifier, which is a configuration error.
        if ($results->count() > 1) {
            throw new MultipleRecordsFoundException($results->count());
        }

        return $results->first();
    }

    /**
     * Binding collections should be returned with the original values as keys.
     * This allows validating the binding when required.
     *
     * @see \Nuwave\Lighthouse\Bind\BindDirective::rules()
     *
     * @param  \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>  $results
     * @param  \Illuminate\Support\Collection<array-key, mixed>  $values
     * @param  \Nuwave\Lighthouse\Bind\BindDefinition<\Illuminate\Database\Eloquent\Model>  $definition
     *
     * @return \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>
     */
    protected function modelCollection(
        EloquentCollection $results,
        IlluminateCollection $values,
        BindDefinition $definition,
    ): EloquentCollection {
        /** @see self::modelInstance() */
        if ($results->count() > $values->unique()->count()) {
            throw new MultipleRecordsFoundException($results->count());
        }

        return $results->keyBy($definition->column);
    }
}

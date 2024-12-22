<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

use function is_iterable;

class ModelBinding
{
    /**
     * @param \Nuwave\Lighthouse\Bind\BindDefinition<\Illuminate\Database\Eloquent\Model> $definition
     */
    public function __invoke(mixed $value, BindDefinition $definition): Model|Collection|null
    {
        $binding = $definition->class::query()
            ->with($definition->with)
            ->whereIn($definition->column, Arr::wrap($value))
            ->get();

        if (is_iterable($value)) {
            return $binding;
        }

        return $binding->first();
    }
}

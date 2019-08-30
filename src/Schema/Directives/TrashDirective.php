<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Laravel\Scout\Builder as ScoutBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class TrashDirective extends BaseDirective implements ArgBuilderDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'trash';
    }

    /**
     * Apply withTrashed, onlyTrashed or withoutTrashed to given $builder if needed.
     * If builder model doesn't support soft deletes, this argument will be ignored!
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param mixed $value
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $value)
    {
        // skip execution, if model doesn't support soft delete
        if ($builder instanceof Relation) {
            $model = $builder->getRelated();
            $query = $builder->getQuery();
        } else {
            $model = $builder instanceof ScoutBuilder
                ? $builder->model
                : $builder->getModel();
            $query = $builder;
        }

        if (! in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($model))) {
            return $builder;
        }

        // apply trashed query modification
        if (! isset($value)) {
            return $builder;
        }

        $trashModificationMethod = "{$value}Trashed";
        $query->{$trashModificationMethod}();

        return $builder;
    }
}

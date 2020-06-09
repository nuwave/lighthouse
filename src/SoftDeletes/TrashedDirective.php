<?php

namespace Nuwave\Lighthouse\SoftDeletes;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class TrashedDirective extends BaseDirective implements ArgBuilderDirective, DefinedDirective
{
    public const MODEL_MUST_USE_SOFT_DELETES = 'Use @trashed only for Model classes that use the SoftDeletes trait.';

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Allows to filter if trashed elements should be fetched.
"""
directive @trashed on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * Apply withTrashed, onlyTrashed or withoutTrashed to given $builder if needed.
     *
     * @param string|null $value "with", "without" or "only"
     */
    public function handleBuilder($builder, $value)
    {
        if ($builder instanceof Relation) {
            $model = $builder->getRelated();
        } elseif ($builder instanceof ScoutBuilder) {
            $model = $builder->model;
        } elseif ($builder instanceof EloquentBuilder) {
            $model = $builder->getModel();
        } else {
            throw new \Exception('Can not get model from builder of class: '.get_class($builder));
        }

        SoftDeletesServiceProvider::assertModelUsesSoftDeletes(
            get_class($model),
            self::MODEL_MUST_USE_SOFT_DELETES
        );

        if (! isset($value)) {
            return $builder;
        }

        $trashedModificationMethod = "{$value}Trashed";
        $builder->{$trashedModificationMethod}();

        return $builder;
    }
}

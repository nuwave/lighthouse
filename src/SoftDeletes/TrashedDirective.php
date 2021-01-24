<?php

namespace Nuwave\Lighthouse\SoftDeletes;

use Exception;
use GraphQL\Exception\InvalidArgument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Scout\ScoutBuilderDirective;
use Nuwave\Lighthouse\Scout\ScoutException;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class TrashedDirective extends BaseDirective implements ArgBuilderDirective, ScoutBuilderDirective
{
    public const MODEL_MUST_USE_SOFT_DELETES = 'Use @trashed only for Model classes that use the SoftDeletes trait.';

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Allows to filter if trashed elements should be fetched.
"""
directive @trashed on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * Apply withTrashed, onlyTrashed or withoutTrashed to given $builder if needed.
     *
     * @param string|null $value "with", "without" or "only"
     */
    public function handleBuilder($builder, $value): object
    {
        if (! $builder instanceof EloquentBuilder) {
            throw new Exception('Can not get model from builder of class: '.get_class($builder));
        }
        $model = $builder->getModel();

        $this->assertModelUsesSoftDeletes($model);

        if ($value === null) {
            return $builder;
        }

        /** @var Builder&SoftDeletes $builder */
        switch ($value) {
            case 'with':
                $builder->withTrashed();

            case 'only':
                $builder->onlyTrashed();

            case 'without':
                $builder->withoutTrashed();

            default:
                throw new InvalidArgument('Unexpected value for Trashed filter');
        }

        return $builder;
    }

    public function handleScoutBuilder(ScoutBuilder $builder, $value)
    {
        $model = $builder->model;

        $this->assertModelUsesSoftDeletes($model);

        if ($value === null) {
            return $builder;
        }

        switch ($value) {
            case 'with':
                $builder->withTrashed();

            case 'only':
                $builder->onlyTrashed();

            default:
                throw new ScoutException('Unexpected value for Trashed filter');
        }
    }

    protected function assertModelUsesSoftDeletes(Model $model): void
    {
        SoftDeletesServiceProvider::assertModelUsesSoftDeletes(
            get_class($model),
            self::MODEL_MUST_USE_SOFT_DELETES
        );
    }
}

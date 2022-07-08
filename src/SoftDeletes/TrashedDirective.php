<?php

namespace Nuwave\Lighthouse\SoftDeletes;

use Exception;
use GraphQL\Exception\InvalidArgument;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
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

    public function handleBuilder($builder, $value): object
    {
        if (! $builder instanceof EloquentBuilder) {
            $notEloquentBuilder = get_class($builder);
            throw new Exception("Can not get model from builder of class: {$notEloquentBuilder}");
        }

        $model = $builder->getModel();
        $this->assertModelUsesSoftDeletes($model);

        if (null === $value) {
            return $builder;
        }

        /** @see \Illuminate\Database\Eloquent\SoftDeletes */
        switch ($value) {
            case 'with':
                // @phpstan-ignore-next-line because it involves mixins
                return $builder->withTrashed();
            case 'only':
                // @phpstan-ignore-next-line because it involves mixins
                return $builder->onlyTrashed();
            case 'without':
                // @phpstan-ignore-next-line because it involves mixins
                return $builder->withoutTrashed();
            default:
                throw new InvalidArgument('Unexpected value for Trashed filter: ' . $value);
        }
    }

    public function handleScoutBuilder(ScoutBuilder $builder, $value): ScoutBuilder
    {
        $model = $builder->model;
        $this->assertModelUsesSoftDeletes($model);

        if (null === $value) {
            return $builder;
        }

        switch ($value) {
            case 'with':
                return $builder->withTrashed();
            case 'only':
                return $builder->onlyTrashed();
            default:
                throw new ScoutException('Unexpected value for Trashed filter: ' . $value);
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

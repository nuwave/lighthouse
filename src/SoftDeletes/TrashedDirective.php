<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\SoftDeletes;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Scout\ScoutBuilderDirective;
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

    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, $value): QueryBuilder|EloquentBuilder|Relation
    {
        if (! $builder instanceof EloquentBuilder) {
            $notEloquentBuilder = $builder::class;
            throw new \Exception("Can not get model from builder of class: {$notEloquentBuilder}");
        }

        $model = $builder->getModel();
        $this->assertModelUsesSoftDeletes($model);

        if ($value === null) {
            return $builder;
        }

        return match ($value) {
            // @phpstan-ignore-next-line mixin not understood
            'with' => $builder->withTrashed(),
            // @phpstan-ignore-next-line mixin not understood
            'only' => $builder->onlyTrashed(),
            // @phpstan-ignore-next-line mixin not understood
            'without' => $builder->withoutTrashed(),
            default => throw new Error("Unexpected value for Trashed filter: {$value}"),
        };
    }

    public function handleScoutBuilder(ScoutBuilder $builder, mixed $value): ScoutBuilder
    {
        $model = $builder->model;
        $this->assertModelUsesSoftDeletes($model);

        if ($value === null) {
            return $builder;
        }

        return match ($value) {
            'with' => $builder->withTrashed(),
            'only' => $builder->onlyTrashed(),
            default => throw new Error("Unexpected value for Trashed filter: {$value}"),
        };
    }

    protected function assertModelUsesSoftDeletes(Model $model): void
    {
        SoftDeletesServiceProvider::assertModelUsesSoftDeletes(
            $model::class,
            self::MODEL_MUST_USE_SOFT_DELETES,
        );
    }
}

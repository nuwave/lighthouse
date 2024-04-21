<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class NestedMorphTo implements ArgResolver
{
    public function __construct(
        /**
         * @var \Illuminate\Database\Eloquent\Relations\MorphTo<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model>  $relation
         */
        protected MorphTo $relation,
    ) {}

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  ArgumentSet  $args
     */
    public function __invoke($parent, $args): void
    {
        // TODO implement create and update once we figure out how to do polymorphic input types https://github.com/nuwave/lighthouse/issues/900

        if ($args->has('connect')) {
            $connectArgs = $args->arguments['connect']->value;
            $connectArgsArguments = $connectArgs->arguments;

            $morphToModel = $this->relation->createModelByType(
                $this->morphTypeValue($connectArgsArguments['type']->value),
            );
            $morphToModel->setAttribute(
                $morphToModel->getKeyName(),
                $connectArgsArguments['id']->value,
            );

            $this->relation->associate($morphToModel);
        }

        NestedBelongsTo::disconnectOrDelete($this->relation, $args);
    }

    protected function morphTypeValue(mixed $morphType): string
    {
        if (PHP_VERSION_ID >= 80100) {
            if ($morphType instanceof \BackedEnum) {
                return $morphType->value;
            }

            if ($morphType instanceof \UnitEnum) {
                return $morphType->name;
            }
        }

        return (string) $morphType;
    }
}

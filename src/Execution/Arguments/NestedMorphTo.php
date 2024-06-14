<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
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
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  ArgumentSet  $args
     */
    public function __invoke($model, $args): void
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
                $value = $morphType->value;
                if (! is_string($value)) {
                    $enumClass = $morphType::class;
                    throw new DefinitionException("Enum {$enumClass} must be string backed.");
                }

                return $value;
            }

            if ($morphType instanceof \UnitEnum) {
                return $morphType->name;
            }
        }

        return (string) $morphType;
    }
}

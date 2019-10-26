<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Execution\ArgumentResolver;
use ReflectionClass;

class SaveModel implements ArgumentResolver
{
    /**
     * @var \Illuminate\Database\Eloquent\Relations\Relation|null
     */
    protected $parentRelation;

    public function __construct(?Relation $parentRelation = null)
    {
        $this->parentRelation = $parentRelation;
    }

    public function __invoke($model, ArgumentSet $args)
    {
        $reflection = new ReflectionClass($model);

        // Extract $morphTo first, as MorphTo extends BelongsTo
        [$remaining, $morphTo] = ArgPartitioner::partitionByRelationType(
            $reflection,
            $args,
            MorphTo::class
        );

        [$remaining, $belongsTo] = ArgPartitioner::partitionByRelationType(
            $reflection,
            $remaining,
            BelongsTo::class
        );

        // Use all the remaining attributes and fill the model
        $model->fill($remaining->toArray());

        foreach($belongsTo->arguments as $relationName => $nestedOperations) {
            $belongsToResolver = new ArgResolver(new NestedBelongsTo($relationName));
            $belongsToResolver($model, $nestedOperations->value);
        }

        foreach($morphTo->arguments as $relationName => $nestedOperations) {
            $morphToResolver = new ArgResolver(new NestedMorphTo($relationName));
            $morphToResolver($model, $nestedOperations->value);
        }

        if ($this->parentRelation instanceof HasOneOrMany) {
            // If we are already resolving a nested create, we might
            // already have an instance of the parent relation available.
            // In that case, use it to set the current model as a child.
            $this->parentRelation->save($model);

            return $model;
        }

        $model->save();

        if ($this->parentRelation instanceof BelongsToMany) {
            $this->parentRelation->syncWithoutDetaching($model);
        }

        return $model;
    }
}

<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Execution\ArgumentResolver;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        // Extract $morphTo first, as MorphTo extends BelongsTo
        [$remaining, $morphTo] = ArgPartitioner::relationMethods(
            $args,
            $model,
            MorphTo::class
        );

        [$remaining, $belongsTo] = ArgPartitioner::relationMethods(
            $remaining,
            $model,
            BelongsTo::class
        );

        // Use all the remaining attributes and fill the model
        $model->fill($remaining->toArray());

        foreach ($belongsTo->arguments as $relationName => $nestedOperations) {
            $belongsToResolver = new ArgResolver(new NestedBelongsTo($relationName));
            $belongsToResolver($model, $nestedOperations->value);
        }

        foreach ($morphTo->arguments as $relationName => $nestedOperations) {
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

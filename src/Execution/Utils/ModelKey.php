<?php

namespace Nuwave\Lighthouse\Execution\Utils;

use Illuminate\Database\Eloquent\Model;

/**
 * Create a model key that concatenates the models fully-qualified class
 * name and key or composite key.
 */
class ModelKey
{
    public static function build(Model $model): string
    {
        return implode(
            ':',
            array_merge(
                [get_class($model)],
                // Might be one or more keys
                (array) ($model->getKey())
            )
        );
    }
}

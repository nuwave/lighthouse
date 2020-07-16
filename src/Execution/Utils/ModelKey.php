<?php

namespace Nuwave\Lighthouse\Execution\Utils;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Create a model key that concatenates the models fully-qualified class
 * name and key or composite key.
 *
 * For example: App\Model\Post:1
 */
class ModelKey
{
    public static function build(Model $model): string
    {
        $key = $model->getKey();

        $modelKey = array_merge(
            [get_class($model)],
            is_array($key) ? $key : [$key]
        );

        return implode(':', $modelKey);
    }
}

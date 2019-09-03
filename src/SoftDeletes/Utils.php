<?php

namespace Nuwave\Lighthouse\SoftDeletes;

use Illuminate\Database\Eloquent\SoftDeletes;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class Utils
{
    /**
     * Ensure a model uses the SoftDeletes trait.
     *
     * @see \Illuminate\Database\Eloquent\SoftDeletes
     *
     * @param  string  $modelClass
     * @param  string  $exceptionMessage
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public static function assertModelUsesSoftDeletes(string $modelClass, string $exceptionMessage): void
    {
        if (
            ! in_array(
                SoftDeletes::class,
                class_uses_recursive($modelClass)
            )
        ) {
            throw new DefinitionException($exceptionMessage);
        }
    }
}

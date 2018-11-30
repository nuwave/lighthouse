<?php

namespace Nuwave\Lighthouse\Support\Traits;

/**
 * Trait HandlesCompositeKey.
 */
trait HandlesCompositeKey
{
    /**
     * Build the model key. Support composite primary keys.
     * Ex: $primaryKey = ['key1', 'key2'];.
     *
     * @param mixed $key
     *
     * @return string
     */
    protected function buildKey($key)
    {
        return (is_array($key))
            ? implode('___', $key)
            : $key;
    }
}

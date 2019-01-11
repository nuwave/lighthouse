<?php

namespace Nuwave\Lighthouse\Support\Traits;

trait HandlesCompositeKey
{
    /**
     * Build a key out of one or more given keys, supporting composite keys.
     *
     * E.g.: $primaryKey = ['key1', 'key2'];.
     *
     * @param  mixed  $key
     * @return string
     */
    protected function buildKey($key): string
    {
        return is_array($key)
            ? implode('___', $key)
            : $key;
    }
}

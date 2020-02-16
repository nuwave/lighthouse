<?php

namespace Tests;

use Illuminate\Cache\ArrayStore;

/**
 * A cache store used for testing.
 *
 * Works like Laravel's usual "array" store, expect
 * it actually serializes/unserializes the values.
 *
 * TODO remove as we support only Laravel 7.x, it allows configuring the array driver like this
 */
class SerializingArrayStore extends ArrayStore
{
    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string|array  $key
     * @return mixed
     */
    public function get($key)
    {
        if (! isset($this->storage[$key])) {
            return;
        }

        $item = $this->storage[$key];

        $expiresAt = $item['expiresAt'] ?? 0;

        if ($expiresAt !== 0 && $this->currentTime() > $expiresAt) {
            $this->forget($key);

            return;
        }

        return unserialize($item['value']);
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  int  $seconds
     * @return bool
     */
    public function put($key, $value, $seconds): bool
    {
        $this->storage[$key] = [
            'value' => serialize($value),
            'expiresAt' => $this->calculateExpiration($seconds),
        ];

        return true;
    }
}

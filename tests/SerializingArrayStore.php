<?php

namespace Tests;

use Illuminate\Cache\ArrayStore;
use Illuminate\Support\InteractsWithTime;

/**
 * A cache store used for testing.
 *
 * Works like Laravel's usual "array" store, expect
 * it actually serializes/unserializes the values.
 *
 * TODO remove once we only support Laravel 7.x plus https://github.com/laravel/framework/pull/31295
 */
final class SerializingArrayStore extends ArrayStore
{
    use InteractsWithTime;

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     *
     * @return mixed The value or null
     */
    public function get($key)
    {
        if (! isset($this->storage[$key])) {
            return null;
        }

        $item = $this->storage[$key];

        $expiresAt = $item['expiresAt'] ?? 0;

        if (0 !== $expiresAt && $this->currentTime() > $expiresAt) {
            $this->forget($key);

            return null;
        }

        return unserialize($item['value']);
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed  $value  Some storable value
     * @param  int  $seconds
     */
    public function put($key, $value, $seconds): bool
    {
        $this->storage[$key] = [
            'value' => serialize($value),
            'expiresAt' => $this->calculateExpiration($seconds),
        ];

        return true;
    }

    /**
     * Get the expiration time of the key.
     *
     * @param  int|float  $seconds
     */
    protected function calculateExpiration($seconds)
    {
        return $this->toTimestamp($seconds);
    }

    /**
     * Get the UNIX timestamp for the given number of seconds.
     *
     * @param  int|float  $seconds
     */
    protected function toTimestamp($seconds)
    {
        return $seconds > 0
            ? $this->availableAt((int) $seconds)
            : 0;
    }
}

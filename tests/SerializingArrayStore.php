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
class SerializingArrayStore extends ArrayStore
{
    use InteractsWithTime;

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
    public function put($key, $value, $seconds)
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
     * @param  int  $seconds
     * @return int
     */
    protected function calculateExpiration($seconds)
    {
        return $this->toTimestamp($seconds);
    }

    /**
     * Get the UNIX timestamp for the given number of seconds.
     *
     * @param  int  $seconds
     * @return int
     */
    protected function toTimestamp($seconds)
    {
        return $seconds > 0 ? $this->availableAt($seconds) : 0;
    }
}

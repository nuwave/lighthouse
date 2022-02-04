<?php

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Support\Arr;

/**
 * Encode and decode pagination cursors.
 *
 * Currently, the underlying pagination Query uses offset based navigation, so
 * this basically just encodes an offset. This is enough to satisfy the constraints
 * that Relay has, but not a clean permanent solution.
 *
 * TODO Implement actual cursor pagination https://github.com/nuwave/lighthouse/issues/311
 */
class Cursor
{
    /**
     * Decode cursor from query arguments.
     *
     * If no 'after' argument is provided or the contents are not a valid base64 string,
     * this will return 0. That will effectively reset pagination, so the user gets the
     * first slice.
     *
     * @param  array<string, mixed>  $args
     */
    public static function decode(array $args): int
    {
        if ($cursor = Arr::get($args, 'after')) {
            return (int) \Safe\base64_decode($cursor);
        }

        return 0;
    }

    /**
     * Encode the given offset to make the implementation opaque.
     */
    public static function encode(int $offset): string
    {
        return base64_encode((string) $offset);
    }
}

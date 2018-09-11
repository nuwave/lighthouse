<?php

namespace Nuwave\Lighthouse\Execution\Utils;

/**
 * Encode and decode pagination cursors.
 *
 * Currently, the underlying pagination Query uses offset based navigation, so
 * this basically just encodes an offset. This is enough to satisfy the constraints
 * that Relay has, but not a clean permanent solution.
 *
 * TODO: Fix this by implementing actual cursor pagination
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
     * @param array $args
     *
     * @return int
     */
    public static function decode(array $args): int
    {
        if(!$cursor = array_get($args, 'after')){
            return 0;
        }

        return (int) base64_decode($cursor);
    }

    /**
     * Encode the given offset to make the implementation opaque.
     *
     */
    public static function encode(int $offset): string
    {
        return base64_encode($offset);
    }
}

<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Support\Arr;

/**
 * Encode and decode pagination cursors.
 *
 * Currently, the underlying pagination query uses offset-based navigation.
 * So this basically just encodes an offset.
 * This is enough to satisfy the constraints that Relay has, but not a clean permanent solution.
 *
 * TODO Implement actual cursor pagination https://github.com/nuwave/lighthouse/issues/311
 */
class Cursor
{
    /**
     * Decode cursor from query arguments.
     *
     * If no 'after' argument is provided, this returns 0.
     * It also returns 0 if the contents are not a valid base64 string.
     * That will effectively reset pagination, so the user gets the first slice.
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

    /** Encode the given offset to make the implementation opaque. */
    public static function encode(int $offset): string
    {
        return base64_encode((string) $offset);
    }
}

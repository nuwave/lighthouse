<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Utils;

class FieldPath
{
    /**
     * Return the dot separated field path without lists.
     *
     * @param  array<int|string>  $path
     */
    public static function withoutLists(array $path): string
    {
        $significantPathSegments = array_filter(
            $path,
            // Ignore numeric path entries, as those signify a list of fields.
            // Combining the queries for lists is the very purpose of the
            // batch loader, so they must not be included.
            static fn ($segment): bool => ! is_numeric($segment),
        );

        // Using . as the separator would combine relations in nested fields with
        // higher up relations using dot notation, matching the field path.
        // We might optimize this in the future to enable batching them anyway,
        // but employ this solution for now, as it preserves correctness.
        return implode('|', $significantPathSegments);
    }
}

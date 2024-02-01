<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Tracing;

use Illuminate\Support\Carbon;

trait TracingUtilities
{
    /**
     * Get the system's highest resolution of time possible.
     *
     * This is either in seconds with microsecond precision (float) or nanoseconds (int).
     */
    public function timestamp(): float|int
    {
        return $this->platformSupportsNanoseconds()
            ? hrtime(true)
            : microtime(true);
    }

    /** Diff the time results to each other and convert to nanoseconds if needed. */
    protected function diffTimeInNanoseconds(float|int $start, float|int $end): int
    {
        if ($this->platformSupportsNanoseconds()) {
            return (int) ($end - $start);
        }

        // Difference is in seconds (with microsecond precision)
        // * 1000 to get to milliseconds
        // * 1000 to get to microseconds
        // * 1000 to get to nanoseconds
        return (int) (($end - $start) * 1000 * 1000 * 1000);
    }

    /** Is the `hrtime` function available to get a nanosecond precision point in time? */
    protected function platformSupportsNanoseconds(): bool
    {
        return function_exists('hrtime');
    }

    protected function formatTimestamp(Carbon $timestamp): string
    {
        return $timestamp->format(Carbon::RFC3339_EXTENDED);
    }
}

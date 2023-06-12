<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Tracing;

use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Execution\ExtensionsResponse;
use Nuwave\Lighthouse\Execution\ResolveInfo;

/**
 * See https://github.com/apollographql/apollo-tracing#response-format.
 */
class Tracing
{
    /** The point in time when the request was initially started. */
    protected Carbon $executionStartAbsolute;

    /**
     * The precise point in time when the request was initially started.
     *
     * This is either in seconds with microsecond precision (float) or nanoseconds (int).
     */
    protected int|float $executionStartPrecise;

    /**
     * Trace entries for a single query execution.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $resolverTraces = [];

    public function handleStartExecution(StartExecution $startExecution): void
    {
        $this->executionStartAbsolute = Carbon::now();
        $this->executionStartPrecise = $this->timestamp();
        $this->resolverTraces = [];
    }

    public function handleBuildExtensionsResponse(BuildExtensionsResponse $buildExtensionsResponse): ExtensionsResponse
    {
        $requestEndAbsolute = Carbon::now();
        $requestEndPrecise = $this->timestamp();

        return new ExtensionsResponse(
            'tracing',
            [
                'version' => 1,
                'startTime' => $this->formatTimestamp($this->executionStartAbsolute),
                'endTime' => $this->formatTimestamp($requestEndAbsolute),
                'duration' => $this->diffTimeInNanoseconds($this->executionStartPrecise, $requestEndPrecise),
                'execution' => [
                    'resolvers' => $this->resolverTraces,
                ],
            ],
        );
    }

    /** Record resolver execution time. */
    public function record(ResolveInfo $resolveInfo, float|int $start, float|int $end): void
    {
        $this->resolverTraces[] = [
            'path' => $resolveInfo->path,
            'parentType' => $resolveInfo->parentType->name,
            'fieldName' => $resolveInfo->fieldName,
            'returnType' => $resolveInfo->returnType->toString(),
            'startOffset' => $this->diffTimeInNanoseconds($this->executionStartPrecise, $start),
            'duration' => $this->diffTimeInNanoseconds($start, $end),
        ];
    }

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

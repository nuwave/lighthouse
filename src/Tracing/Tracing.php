<?php

namespace Nuwave\Lighthouse\Tracing;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Execution\ExtensionsResponse;

/**
 * See https://github.com/apollographql/apollo-tracing#response-format.
 */
class Tracing
{
    /**
     * The point in time when the request was initially started.
     *
     * @var \Illuminate\Support\Carbon
     */
    protected $executionStartAbsolute;

    /**
     * The precise point in time when the request was initially started.
     *
     * This is either in seconds with microsecond precision (float) or nanoseconds (int).
     *
     * @var float|int
     */
    protected $executionStartPrecise;

    /**
     * Trace entries for a single query execution.
     *
     * @var array<int, array<string, mixed>>
     */
    protected $resolverTraces = [];

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
            ]
        );
    }

    /**
     * Record resolver execution time.
     *
     * @param  float|int  $start
     * @param  float|int  $end
     */
    public function record(ResolveInfo $resolveInfo, $start, $end): void
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
     *
     * @return float|int
     */
    public function timestamp()
    {
        return $this->platformSupportsNanoseconds()
            ? hrtime(true)
            : microtime(true);
    }

    /**
     * Diff the time results to each other and convert to nanoseconds if needed.
     *
     * @param  float|int  $start
     * @param  float|int  $end
     */
    protected function diffTimeInNanoseconds($start, $end): int
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

    /**
     * Is the `hrtime` function available to get a nanosecond precision point in time?
     */
    protected function platformSupportsNanoseconds(): bool
    {
        return function_exists('hrtime');
    }

    protected function formatTimestamp(Carbon $timestamp): string
    {
        return $timestamp->format(Carbon::RFC3339_EXTENDED);
    }
}

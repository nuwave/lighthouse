<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Tracing\ApolloTracing;

use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Execution\ExtensionsResponse;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Tracing\Tracing;
use Nuwave\Lighthouse\Tracing\TracingUtilities;

/** See https://github.com/apollographql/apollo-tracing#response-format. */
class ApolloTracing implements Tracing
{
    use TracingUtilities;

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

    public function handleStartRequest(StartRequest $startRequest): void {}

    public function handleStartExecution(StartExecution $startExecution): void
    {
        $this->executionStartAbsolute = Carbon::now();
        $this->executionStartPrecise = $this->timestamp();
        $this->resolverTraces = [];
    }

    public function handleBuildExtensionsResponse(BuildExtensionsResponse $buildExtensionsResponse): ?ExtensionsResponse
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
}

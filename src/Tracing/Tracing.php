<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Tracing;

use Google\Protobuf\Timestamp;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Execution\ExtensionsResponse;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Nuwave\Lighthouse\Tracing\Proto\Trace;
use Nuwave\Lighthouse\Tracing\Proto\Trace\Node;

/**
 * See https://github.com/apollographql/apollo-tracing#response-format.
 */
class Tracing
{
    protected bool $isSubgraph;

    protected ?Trace $trace = null;

    protected float|int $requestStartPrecise;

    /** @var array<string,Node> */
    protected array $nodes = [];

    public function __construct()
    {
        $app = Container::getInstance();
        assert($app instanceof Application);
        $this->isSubgraph = $app->providerIsLoaded(FederationServiceProvider::class);
    }

    public function handleStartRequest(StartRequest $startRequest): void
    {
        if ($this->isSubgraph && $startRequest->request->header('apollo-federation-include-trace') !== 'ftv1') {
            return;
        }

        $this->trace = new Trace();
        $this->trace->setRoot(new Node());
        $this->trace->setFieldExecutionWeight(1);
    }

    public function handleStartExecution(StartExecution $startExecution): void
    {
        if ($this->trace === null) {
            return;
        }

        $this->requestStartPrecise = $this->timestamp();

        $this->trace->setStartTime(
            (new Timestamp())
                ->setSeconds($startExecution->moment->getTimestamp())
                ->setNanos($startExecution->moment->micro * 1000)
        );
    }

    public function handleBuildExtensionsResponse(BuildExtensionsResponse $buildExtensionsResponse): ?ExtensionsResponse
    {
        if ($this->trace === null) {
            return null;
        }

        $requestEnd = Carbon::now();
        $requestEndPrecise = $this->timestamp();

        $this->trace->setEndTime(
            (new Timestamp())
                ->setSeconds((int) $requestEnd->getTimestamp())
                ->setNanos($requestEnd->micro * 1000)
        );
        $this->trace->setDurationNs($this->diffTimeInNanoseconds($this->requestStartPrecise, $requestEndPrecise));

        return new ExtensionsResponse(
            'ftv1',
            base64_encode($this->trace->serializeToString()),
        );
    }

    /** Record resolver execution time. */
    public function record(ResolveInfo $resolveInfo, float|int $start, float|int $end): void
    {
        if ($this->trace === null) {
            return;
        }

        $node = $this->newNode($resolveInfo->path);
        $node->setType($resolveInfo->returnType->toString());
        $node->setParentType($resolveInfo->parentType->toString());
        $node->setStartTime($this->diffTimeInNanoseconds($start, $this->requestStartPrecise));
        $node->setEndTime($this->diffTimeInNanoseconds($end, $this->requestStartPrecise));
    }

    /** @param  array<int, int|string>  $path */
    protected function newNode(array $path): Node
    {
        $node = new Node();

        $field = $path[count($path) - 1];
        if (is_int($field)) {
            $node->setIndex($field);
        } else {
            $node->setResponseName($field);
        }

        $this->nodes[implode('.', $path)] = $node;

        $parentNode = $this->ensureParentNode($path);

        if ($parentNode !== null) {
            $parentNode->getChild()[] = $node;
        }

        return $node;
    }

    /** @param  array<int, int|string>  $path */
    protected function ensureParentNode(array $path): ?Node
    {
        if ($path === []) {
            return null;
        }

        if (count($path) === 1) {
            return $this->trace?->getRoot();
        }

        $parentPath = array_slice($path, 0, -1);

        $parentNode = $this->nodes[implode('.', $parentPath)] ?? null;

        return $parentNode ?? $this->newNode($parentPath);
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

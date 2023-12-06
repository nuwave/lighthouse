<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Tracing\FederatedTracing;

use Google\Protobuf\Timestamp;
use GraphQL\Error\Error;
use GraphQL\Language\SourceLocation;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Execution\ExtensionsResponse;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace;
use Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace\Location;
use Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace\Node;
use Nuwave\Lighthouse\Tracing\Tracing;
use Nuwave\Lighthouse\Tracing\TracingUtilities;

/** See https://www.apollographql.com/docs/federation/metrics/. */
class FederatedTracing implements Tracing
{
    use TracingUtilities;

    public const V1 = 'ftv1';

    protected bool $isSubgraph;

    protected bool $enabled = true;

    protected Trace $trace;

    /**
     * The precise point in time when the request was initially started.
     *
     * This is either in seconds with microsecond precision (float) or nanoseconds (int).
     */
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
        if ($this->isSubgraph && $startRequest->request->header('apollo-federation-include-trace') !== self::V1) {
            $this->enabled = false;

            return;
        }

        $this->enabled = true;
    }

    public function handleStartExecution(StartExecution $startExecution): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->requestStartPrecise = $this->timestamp();
        $this->nodes = [];

        $this->trace = new Trace();
        $this->trace->setRoot(new Node());
        $this->trace->setFieldExecutionWeight(1);
        $this->trace->setStartTime(
            (new Timestamp())
                ->setSeconds($startExecution->moment->getTimestamp())
                ->setNanos($startExecution->moment->micro * 1000),
        );
    }

    public function handleBuildExtensionsResponse(BuildExtensionsResponse $buildExtensionsResponse): ?ExtensionsResponse
    {
        if (! $this->enabled) {
            return null;
        }

        $requestEnd = Carbon::now();
        $requestEndPrecise = $this->timestamp();

        $this->trace->setEndTime(
            (new Timestamp())
                ->setSeconds($requestEnd->getTimestamp())
                ->setNanos($requestEnd->micro * 1000),
        );
        $this->trace->setDurationNs($this->diffTimeInNanoseconds($this->requestStartPrecise, $requestEndPrecise));

        foreach ($buildExtensionsResponse->result->errors as $resultError) {
            $this->recordError($resultError);
        }

        assert($this->trace !== null);

        return new ExtensionsResponse(
            self::V1,
            base64_encode($this->trace->serializeToString()),
        );
    }

    /** Record resolver execution time. */
    public function record(ResolveInfo $resolveInfo, float|int $start, float|int $end): void
    {
        if (! $this->enabled) {
            return;
        }

        $node = $this->findOrNewNode($resolveInfo->path);
        $node->setType($resolveInfo->returnType->toString());
        $node->setParentType($resolveInfo->parentType->toString());
        $node->setStartTime($this->diffTimeInNanoseconds($this->requestStartPrecise, $start));
        $node->setEndTime($this->diffTimeInNanoseconds($this->requestStartPrecise, $end));
    }

    protected function recordError(Error $error): void
    {
        if (! $this->enabled) {
            return;
        }

        $traceError = new Trace\Error();
        $traceError->setMessage($error->isClientSafe() ? $error->getMessage() : 'Internal server error');
        $traceError->setLocation(array_map(static function (SourceLocation $sourceLocation): Location {
            $location = new Location();
            $location->setLine($sourceLocation->line);
            $location->setColumn($sourceLocation->column);

            return $location;
        }, $error->getLocations()));

        $node = $this->findOrNewNode($error->getPath() ?? []);
        $node->setError([$traceError]);
    }

    /** @param  array<int, int|string>  $path */
    protected function findOrNewNode(array $path): Node
    {
        assert($this->trace !== null);
        assert($this->trace->getRoot() !== null);

        if ($path === []) {
            return $this->trace->getRoot();
        }

        $pathKey = implode('.', $path);

        return $this->nodes[$pathKey] ??= $this->newNode($path);
    }

    /** @param  non-empty-array<int, int|string>  $path */
    protected function newNode(array $path): Node
    {
        $node = new Node();

        $field = $path[count($path) - 1];
        if (is_int($field)) {
            $node->setIndex($field);
        } else {
            $node->setResponseName($field);
        }

        $parentNode = $this->ensureParentNode($path);
        $parentNode->getChild()[] = $node;

        return $node;
    }

    /** @param  non-empty-array<int, int|string>  $path */
    protected function ensureParentNode(array $path): Node
    {
        if (count($path) === 1) {
            $rootNode = $this->trace->getRoot();
            assert($rootNode !== null);

            return $rootNode;
        }

        $parentPath = array_slice($path, 0, -1);

        $parentNode = $this->nodes[implode('.', $parentPath)] ?? null;

        return $parentNode ?? $this->findOrNewNode($parentPath);
    }
}

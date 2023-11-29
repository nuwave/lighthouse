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

/**
 * See https://github.com/apollographql/apollo-tracing#response-format.
 */
class FederatedTracing implements Tracing
{
    use TracingUtilities;

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
                ->setNanos($startExecution->moment->micro * 1000),
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
                ->setSeconds($requestEnd->getTimestamp())
                ->setNanos($requestEnd->micro * 1000),
        );
        $this->trace->setDurationNs($this->diffTimeInNanoseconds($this->requestStartPrecise, $requestEndPrecise));

        foreach ($buildExtensionsResponse->result->errors as $resultError) {
            $this->recordError($resultError);
        }

        assert($this->trace !== null);

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

        $node = $this->findOrNewNode($resolveInfo->path);
        $node->setType($resolveInfo->returnType->toString());
        $node->setParentType($resolveInfo->parentType->toString());
        $node->setStartTime($this->diffTimeInNanoseconds($this->requestStartPrecise, $start));
        $node->setEndTime($this->diffTimeInNanoseconds($this->requestStartPrecise, $end));
    }

    protected function recordError(Error $error): void
    {
        if ($this->trace === null) {
            return;
        }

        $traceError = new Trace\Error();
        $traceError->setMessage($error->getMessage());
        $traceError->setLocation(array_map(static function (SourceLocation $sourceLocation): Location {
            $location = new Location();
            $location->setLine($sourceLocation->line);
            $location->setColumn($sourceLocation->column);

            return $location;
        }, $error->getLocations()));
        $traceError->setJson(json_encode($error->jsonSerialize(), JSON_THROW_ON_ERROR));

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

        if (isset($this->nodes[$pathKey])) {
            return $this->nodes[$pathKey];
        }

        $node = new Node();

        $field = $path[count($path) - 1];
        if (is_int($field)) {
            $node->setIndex($field);
        } else {
            $node->setResponseName($field);
        }

        $this->nodes[$pathKey] = $node;

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

        return $parentNode ?? $this->findOrNewNode($parentPath);
    }
}

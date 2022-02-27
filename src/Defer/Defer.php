<?php

namespace Nuwave\Lighthouse\Defer;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Symfony\Component\HttpFoundation\Response;

class Defer implements CreatesResponse
{
    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\CanStreamResponse
     */
    protected $stream;

    /**
     * @var \Nuwave\Lighthouse\GraphQL
     */
    protected $graphQL;

    /**
     * @var \Nuwave\Lighthouse\Events\StartExecution
     */
    protected $startExecution;

    /**
     * A map from paths to deferred resolvers.
     *
     * @var array<string, \Closure(): mixed>
     */
    protected $deferred = [];

    /**
     * Paths resolved during the current nesting of defers.
     *
     * @var array<int, mixed>
     */
    protected $resolved = [];

    /**
     * The entire result of resolving the query up until the current nesting.
     *
     * @var array<string, mixed>
     */
    protected $result = [];

    /**
     * Should further deferring happen?
     *
     * @var bool
     */
    protected $shouldDeferFurther = true;

    /**
     * Are we currently streaming deferred results?
     *
     * @var bool
     */
    protected $isStreaming = false;

    /**
     * @var float|int
     */
    protected $maxExecutionTime = 0;

    /**
     * @var int
     */
    protected $maxNestedFields = 0;

    public function __construct(CanStreamResponse $stream, GraphQL $graphQL, ConfigRepository $config)
    {
        $this->stream = $stream;
        $this->graphQL = $graphQL;

        $executionTime = $config->get('lighthouse.defer.max_execution_ms', 0);
        if ($executionTime > 0) {
            $this->maxExecutionTime = microtime(true) + $executionTime * 1000;
        }

        $this->maxNestedFields = $config->get('lighthouse.defer.max_nested_fields', 0);
    }

    public function handleStartExecution(StartExecution $startExecution): void
    {
        $this->startExecution = $startExecution;
    }

    /**
     * Register deferred field.
     *
     * @param  \Closure(): mixed  $resolver
     *
     * @return mixed the data if it is already available
     */
    public function defer(Closure $resolver, string $path)
    {
        $data = $this->getData($path);
        if (null !== $data) {
            return $data;
        }

        // If we have been here before, now is the time to resolve this field
        $deferredResolver = $this->deferred[$path] ?? null;
        if ($deferredResolver) {
            return $this->resolve($deferredResolver, $path);
        }

        if (! $this->shouldDeferFurther) {
            return $this->resolve($resolver, $path);
        }

        $this->deferred[$path] = $resolver;

        return null;
    }

    /**
     * @return mixed The data at the path
     */
    protected function getData(string $path)
    {
        return Arr::get($this->result, "data.{$path}");
    }

    /**
     * @param  \Closure(): mixed  $resolver
     *
     * @return mixed The loaded data
     */
    protected function resolve(Closure $resolver, string $path)
    {
        unset($this->deferred[$path]);
        $this->resolved[] = $path;

        return $resolver();
    }

    /**
     * @param  \Closure(): mixed  $originalResolver
     *
     * @return mixed The loaded data
     */
    public function findOrResolve(Closure $originalResolver, string $path)
    {
        if ($this->hasData($path)) {
            return $this->getData($path);
        }

        return $originalResolver();
    }

    protected function hasData(string $path): bool
    {
        return Arr::has($this->result, "data.{$path}");
    }

    /**
     * Return either a final response or a stream of responses.
     *
     * @param  array<string, mixed>  $result
     *
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function createResponse(array $result): Response
    {
        if (! $this->hasRemainingDeferred()) {
            return response($result);
        }

        $this->result = $result;
        $this->isStreaming = true;

        return response()->stream(
            function (): void {
                $this->stream();

                $nested = 1;
                while (
                    $this->hasRemainingDeferred()
                    && ! $this->maxExecutionTimeReached()
                    && ! $this->maxNestedFieldsResolved($nested)
                ) {
                    ++$nested;
                    $this->executeDeferred();
                }

                // We've hit the max execution time or max nested levels of deferred fields.
                $this->shouldDeferFurther = false;

                // We process remaining deferred fields, but are no longer allowing additional
                // fields to be deferred.
                if ($this->hasRemainingDeferred()) {
                    $this->executeDeferred();
                }
            },
            200,
            [
                'X-Accel-Buffering' => 'no',
                'Content-Type' => 'multipart/mixed; boundary="-"',
            ]
        );
    }

    protected function hasRemainingDeferred(): bool
    {
        return [] !== $this->deferred;
    }

    protected function stream(): void
    {
        $this->stream->stream(
            $this->result,
            $this->resolved,
            ! $this->hasRemainingDeferred()
        );
    }

    /**
     * Check if we reached the maximum execution time.
     */
    protected function maxExecutionTimeReached(): bool
    {
        if (0 === $this->maxExecutionTime) {
            return false;
        }

        return $this->maxExecutionTime <= microtime(true);
    }

    /**
     * Check if the maximum number of nested field has been resolved.
     */
    protected function maxNestedFieldsResolved(int $nested): bool
    {
        if (0 === $this->maxNestedFields) {
            return false;
        }

        return $this->maxNestedFields <= $nested;
    }

    protected function executeDeferred(): void
    {
        $executionResult = $this->graphQL->executeParsedQuery(
            $this->startExecution->query,
            $this->startExecution->context,
            $this->startExecution->variables,
            null,
            $this->startExecution->operationName
        );

        $this->result = $this->graphQL->serializable($executionResult);
        $this->stream();

        $this->resolved = [];
    }

    public function setMaxExecutionTime(float $time): void
    {
        $this->maxExecutionTime = $time;
    }

    public function setMaxNestedFields(int $max): void
    {
        $this->maxNestedFields = $max;
    }
}

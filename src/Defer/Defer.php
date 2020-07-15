<?php

namespace Nuwave\Lighthouse\Defer;

use Closure;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
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
     * @var mixed[]
     */
    protected $result = [];

    /**
     * @var mixed[]
     */
    protected $deferred = [];

    /**
     * @var mixed[]
     */
    protected $resolved = [];

    /**
     * @var bool
     */
    protected $acceptFurtherDeferring = true;

    /**
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

    public function __construct(CanStreamResponse $stream, GraphQL $graphQL)
    {
        $this->stream = $stream;
        $this->graphQL = $graphQL;
        $this->maxNestedFields = config('lighthouse.defer.max_nested_fields', 0);
    }

    /**
     * Set the tracing directive on all fields of the query to enable tracing them.
     */
    public function handleManipulateAST(ManipulateAST $manipulateAST): void
    {
        ASTHelper::attachDirectiveToObjectTypeFields(
            $manipulateAST->documentAST,
            PartialParser::directive(/** @lang GraphQL */ '@deferrable')
        );

        $manipulateAST->documentAST->setDirectiveDefinition(
            PartialParser::directiveDefinition(/** @lang GraphQL */ '
"""
Use this directive on expensive or slow fields to resolve them asynchronously.
Must not be placed upon:
- Non-Nullable fields
- Mutation root fields
"""
directive @defer(if: Boolean = true) on FIELD
')
        );
    }

    public function isStreaming(): bool
    {
        return $this->isStreaming;
    }

    /**
     * Register deferred field.
     *
     * @return mixed The data if it is already available.
     */
    public function defer(Closure $resolver, string $path)
    {
        if ($data = Arr::get($this->result, "data.{$path}")) {
            return $data;
        }

        if ($this->isDeferred($path) || ! $this->acceptFurtherDeferring) {
            return $this->resolve($resolver, $path);
        }

        $this->deferred[$path] = $resolver;
    }

    /**
     * @return mixed The loaded data.
     */
    public function findOrResolve(Closure $originalResolver, string $path)
    {
        if (! $this->hasData($path)) {
            if (isset($this->deferred[$path])) {
                unset($this->deferred[$path]);
            }

            return $this->resolve($originalResolver, $path);
        }

        return Arr::get($this->result, "data.{$path}");
    }

    /**
     * Resolve field with data or resolver.
     *
     * @return mixed The result of calling the resolver.
     */
    public function resolve(Closure $originalResolver, string $path)
    {
        $isDeferred = $this->isDeferred($path);
        $resolver = $isDeferred
            ? $this->deferred[$path]
            : $originalResolver;

        if ($isDeferred) {
            $this->resolved[] = $path;

            unset($this->deferred[$path]);
        }

        return $resolver();
    }

    public function isDeferred(string $path): bool
    {
        return isset($this->deferred[$path]);
    }

    public function hasData(string $path): bool
    {
        return Arr::has($this->result, "data.{$path}");
    }

    /**
     * Return either a final response or a stream of responses.
     *
     * @param  mixed[]  $result
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function createResponse(array $result): Response
    {
        if (empty($this->deferred)) {
            return response($result);
        }

        return response()->stream(
            function () use ($result): void {
                $nested = 1;
                $this->result = $result;
                $this->isStreaming = true;
                $this->stream->stream($result, [], empty($this->deferred));

                if ($executionTime = config('lighthouse.defer.max_execution_ms', 0)) {
                    $this->maxExecutionTime = microtime(true) + $executionTime * 1000;
                }

                while (
                    count($this->deferred)
                    && ! $this->executionTimeExpired()
                    && ! $this->maxNestedFieldsResolved($nested)
                ) {
                    $nested++;
                    $this->executeDeferred();
                }

                // We've hit the max execution time or max nested levels of deferred fields.
                // We process remaining deferred fields, but are no longer allowing additional
                // fields to be deferred.
                if (count($this->deferred)) {
                    $this->acceptFurtherDeferring = false;
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

    public function setMaxExecutionTime(float $time): void
    {
        $this->maxExecutionTime = $time;
    }

    /**
     * Override max nested fields.
     */
    public function setMaxNestedFields(int $max): void
    {
        $this->maxNestedFields = $max;
    }

    /**
     * Check if the maximum execution time has expired.
     */
    protected function executionTimeExpired(): bool
    {
        if ($this->maxExecutionTime === 0) {
            return false;
        }

        return $this->maxExecutionTime <= microtime(true);
    }

    /**
     * Check if the maximum number of nested field has been resolved.
     */
    protected function maxNestedFieldsResolved(int $nested): bool
    {
        if ($this->maxNestedFields === 0) {
            return false;
        }

        return $nested >= $this->maxNestedFields;
    }

    /**
     * Execute deferred fields.
     */
    protected function executeDeferred(): void
    {
        $this->result = app()->call(
            [$this->graphQL, 'executeRequest']
        );

        $this->stream->stream(
            $this->result,
            $this->resolved,
            empty($this->deferred)
        );

        $this->resolved = [];
    }
}

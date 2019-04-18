<?php

namespace Nuwave\Lighthouse\Defer;

use Closure;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Symfony\Component\HttpFoundation\Response;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;

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
     * @var int
     */
    protected $maxExecutionTime = 0;

    /**
     * @var int
     */
    protected $maxNestedFields = 0;

    /**
     * @param  \Nuwave\Lighthouse\Support\Contracts\CanStreamResponse  $stream
     * @param  \Nuwave\Lighthouse\GraphQL  $graphQL
     * @return void
     */
    public function __construct(CanStreamResponse $stream, GraphQL $graphQL)
    {
        $this->stream = $stream;
        $this->graphQL = $graphQL;
        $this->maxNestedFields = config('lighthouse.defer.max_nested_fields', 0);
    }

    /**
     * Set the tracing directive on all fields of the query to enable tracing them.
     *
     * @param  \Nuwave\Lighthouse\Events\ManipulateAST  $ManipulateAST
     * @return void
     */
    public function handleManipulateAST(ManipulateAST $ManipulateAST): void
    {
        $ManipulateAST->documentAST = ASTHelper::attachDirectiveToObjectTypeFields(
            $ManipulateAST->documentAST,
            PartialParser::directive('@deferrable')
        );

        $ManipulateAST->documentAST->setDefinition(
            PartialParser::directiveDefinition('directive @defer(if: Boolean) on FIELD')
        );
    }

    /**
     * @return bool
     */
    public function isStreaming(): bool
    {
        return $this->isStreaming;
    }

    /**
     * Register deferred field.
     *
     * @param  \Closure  $resolver
     * @param  string  $path
     * @return mixed
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
     * @param  \Closure  $originalResolver
     * @param  string  $path
     * @return mixed
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
     * @param  \Closure  $originalResolver
     * @param  string  $path
     * @return mixed
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

    /**
     * @param  string  $path
     * @return bool
     */
    public function isDeferred(string $path): bool
    {
        return isset($this->deferred[$path]);
    }

    /**
     * @param  string  $path
     * @return bool
     */
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
                    $this->maxExecutionTime = microtime(true) + ($executionTime * 1000);
                }

                // TODO: Allow nested_levels to be set in config
                // to break out of loop early.
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
                // TODO: Allow headers to be set in config
                'X-Accel-Buffering' => 'no',
                'Content-Type' => 'multipart/mixed; boundary="-"',
            ]
        );
    }

    /**
     * @param  int  $time
     * @return void
     */
    public function setMaxExecutionTime(int $time): void
    {
        $this->maxExecutionTime = $time;
    }

    /**
     * Override max nested fields.
     *
     * @param  int  $max
     * @return void
     */
    public function setMaxNestedFields(int $max): void
    {
        $this->maxNestedFields = $max;
    }

    /**
     * Check if the maximum execution time has expired.
     *
     * @return bool
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
     *
     * @param  int  $nested
     * @return bool
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
     *
     * @return void
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

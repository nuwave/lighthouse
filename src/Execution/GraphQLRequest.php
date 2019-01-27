<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class GraphQLRequest
{
    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The current batch index.
     *
     * Is null if we are not resolving a batched query.
     *
     * @var int|null
     */
    protected $batchIndex;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        // If the request has neither a query, nor an operationName
        // we might be dealing with a batched query
        if (! $request->hasAny(['query', 'operationName'])) {
            $this->batchIndex = 0;
        }
    }

    /**
     * Get the contained GraphQL query string.
     *
     * @return string
     */
    public function query(): string
    {
        return $this->getInputByKey('query');
    }

    /**
     * Get the given variables for the query.
     *
     * @return mixed[]
     */
    public function variables(): array
    {
        $variables = $this->getInputByKey('variables');

        if (is_string($variables)) {
            return json_decode($variables, true) ?? [];
        }

        return $variables ?? [];
    }

    /**
     * Get the operationName of the current request.
     *
     * @return string|null
     */
    public function operationName(): ?string
    {
        return $this->getInputByKey('operationName');
    }

    /**
     * Construct a GraphQLContext from the underlying request.
     *
     * @return \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
     */
    public function context(): GraphQLContext
    {
        /** @var \Nuwave\Lighthouse\Support\Contracts\CreatesContext $contextFactory */
        $contextFactory = app(CreatesContext::class);

        return $contextFactory->generate($this->request);
    }

    /**
     * Is the current query a batched query?
     *
     * @return bool
     */
    public function isBatched(): bool
    {
        return ! is_null($this->batchIndex);
    }

    /**
     * Advance the batch index and indicate if there are more batches to process.
     *
     * @return bool
     */
    public function advanceBatchIndex(): bool
    {
        if ($this->hasMoreBatches()) {
            ++$this->batchIndex;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Are there more batched queries to process?
     *
     * @return bool
     */
    protected function hasMoreBatches(): bool
    {
        return count($this->request->input()) - 1 > $this->batchIndex;
    }

    /**
     * Get the index of the current batch.
     *
     * Returns null if we are not resolving a batched query.
     *
     * @return int|null
     */
    public function batchIndex()
    {
        return $this->batchIndex;
    }

    /**
     * If we are dealing with a batched request, this gets the
     * contents of the currently resolving batch index.
     *
     * @param string $key
     *
     * @return array|string|null
     */
    protected function getInputByKey(string $key)
    {
        return $this->request->input($key)
            ?? $this->request->input("{$this->batchIndex}.{$key}");
    }
}

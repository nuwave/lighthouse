<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Http\Request;

abstract class BaseRequest implements GraphQLRequest
{
    /**
     * The current batch index.
     *
     * Is null if we are not resolving a batched query.
     *
     * @var int|null
     */
    protected $batchIndex;

    /**
     * Get the contents of a field by key.
     *
     * This is expected to take batched requests into consideration.
     *
     * @param  string  $key
     * @return array|string|null
     */
    abstract protected function fieldValue(string $key);

    /**
     * Are there more batched queries to process?
     *
     * @return bool
     */
    abstract protected function hasMoreBatches(): bool;

    /**
     * Construct this from a HTTP request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    abstract public function __construct(Request $request);

    /**
     * Get the contained GraphQL query string.
     *
     * @return string
     */
    public function query(): string
    {
        return $this->fieldValue('query');
    }

    /**
     * Get the operationName of the current request.
     *
     * @return string|null
     */
    public function operationName(): ?string
    {
        return $this->fieldValue('operationName');
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
     * Get the index of the current batch.
     *
     * Returns null if we are not resolving a batched query.
     *
     * @return int|null
     */
    public function batchIndex(): ?int
    {
        return $this->batchIndex;
    }

    /**
     * Advance the batch index and indicate if there are more batches to process.
     *
     * @return bool
     */
    public function advanceBatchIndex(): bool
    {
        if ($result = $this->hasMoreBatches()) {
            $this->batchIndex++;
        }

        return $result;
    }
}

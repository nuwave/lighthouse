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
     * @return mixed The value of the field
     */
    abstract protected function fieldValue(string $key);

    /**
     * Are there more batched queries to process?
     */
    abstract protected function hasMoreBatches(): bool;

    /**
     * Construct this from a HTTP request.
     *
     * @return void
     */
    abstract public function __construct(Request $request);

    /**
     * Get the contained GraphQL query string.
     */
    public function query(): string
    {
        return $this->fieldValue('query') ?? '';
    }

    /**
     * Get the operationName of the current request.
     */
    public function operationName(): ?string
    {
        return $this->fieldValue('operationName');
    }

    /**
     * Is the current query a batched query?
     */
    public function isBatched(): bool
    {
        return ! is_null($this->batchIndex);
    }

    /**
     * Get the index of the current batch.
     *
     * Returns null if we are not resolving a batched query.
     */
    public function batchIndex(): ?int
    {
        return $this->batchIndex;
    }

    /**
     * Advance the batch index and indicate if there are more batches to process.
     */
    public function advanceBatchIndex(): bool
    {
        if ($result = $this->hasMoreBatches()) {
            $this->batchIndex++;
        }

        return $result;
    }
}

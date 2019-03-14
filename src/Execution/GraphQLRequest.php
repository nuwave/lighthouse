<?php

namespace Nuwave\Lighthouse\Execution;

interface GraphQLRequest
{
    /**
     * Get the contained GraphQL query string.
     *
     * @return string
     */
    public function query(): string;

    /**
     * Get the given variables for the query.
     *
     * @return mixed[]
     */
    public function variables(): array;

    /**
     * Get the operationName of the current request.
     *
     * @return string|null
     */
    public function operationName(): ?string;

    /**
     * Is the current query a batched query?
     *
     * @return bool
     */
    public function isBatched(): bool;

    /**
     * Advance the batch index and indicate if there are more batches to process.
     *
     * @return bool
     */
    public function advanceBatchIndex(): bool;

    /**
     * Get the index of the current batch.
     *
     * Returns null if we are not resolving a batched query.
     *
     * @return int|null
     */
    public function batchIndex(): ?int;
}

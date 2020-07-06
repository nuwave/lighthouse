<?php

namespace Nuwave\Lighthouse\Execution;

interface GraphQLRequest
{
    /**
     * Get the contained GraphQL query string.
     */
    public function query(): string;

    /**
     * Get the given variables for the query.
     *
     * @return array<string, mixed>
     */
    public function variables(): array;

    /**
     * Get the operationName of the current request.
     */
    public function operationName(): ?string;

    /**
     * Is the current query a batched query?
     */
    public function isBatched(): bool;

    /**
     * Advance the batch index and indicate if there are more batches to process.
     */
    public function advanceBatchIndex(): bool;

    /**
     * Get the index of the current batch.
     *
     * Returns null if we are not resolving a batched query.
     */
    public function batchIndex(): ?int;
}

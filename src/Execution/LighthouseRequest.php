<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use GraphQL\Error\InvariantViolation;

class LighthouseRequest implements GraphQLRequest
{
    /**
     * The incoming HTTP request.
     *
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
     * @param  Request  $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;

        if ($this->isMultipartRequest()) {
            // If operations is 0-indexed, we assume we are resolving a batched query
            if (! is_null($this->getInputByKey(0))) {
                $this->batchIndex = 0;
            }
        } else {
            // If the request has neither a query, nor an operationName,
            // we assume we are resolving a batched query.
            if (! $request->hasAny('query', 'operationName')) {
                $this->batchIndex = 0;
            }
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
        $variables = (array) $this->getInputByKey('variables');

        if ($this->isMultipartRequest()) {
            $variables = $this->mapUploadedFiles($variables);
        }

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
        if ($result = $this->hasMoreBatches()) {
            $this->batchIndex++;
        }

        return $result;
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
    public function batchIndex(): ?int
    {
        return $this->batchIndex;
    }

    /**
     * If we are dealing with a batched request, this gets the
     * contents of the currently resolving batch index.
     *
     * @param  string  $key
     * @return array|string|null
     */
    protected function getInputByKey(string $key)
    {
        if ($this->isMultipartRequest()) {
            $operations = json_decode($this->request->input('operations'), true);

            return $operations[$key]
                ?? $operations[$this->batchIndex][$key]
                ?? null;
        }

        return $this->request->input($key)
            ?? $this->request->input("{$this->batchIndex}.{$key}");
    }

    /**
     * Maps uploaded files to the variables array.
     *
     * @param  array  $variables
     * @return array
     */
    protected function mapUploadedFiles(array $variables): array
    {
        if ($this->isMultipartRequest($this->request)) {
            $map = json_decode($this->request->input('map'), true);

            if (! isset($map)) {
                throw new InvariantViolation(
                    'Could not find a valid map, be sure to conform to GraphQL multipart request specification: https://github.com/jaydenseric/graphql-multipart-request-spec'
                );
            }

            foreach ($map as $fileKey => $locations) {
                foreach ($locations as $location) {

                    // Check if location is inside current batchIndex.
                    // Set to true, if query is not batched
                    $insideCurrentQuery = ! $this->isBatched();
                    if ($this->isBatched()) {
                        $insideCurrentQuery = strpos($location, (string) $this->batchIndex()) === 0;
                    }

                    // Check if this file is mapped to the current query or query is not batched
                    if ($insideCurrentQuery) { // TODO Check is non-batched works
                        // Remove index from location, if query is batched
                        if ($this->isBatched()) {
                            $location = preg_replace('/'.$this->batchIndex.'./', '', $location, 1);
                        }

                        $items = &$variables;
                        $location = preg_replace('/variables./', '', $location, 1);
                        $location = explode('.', $location);
                        foreach ($location as $key) {
                            if (! isset($items[$key]) || ! is_array($items[$key])) {
                                $items[$key] = [];
                            }
                            $items = &$items[$key];
                        }
                        $items = $this->request->file($fileKey);
                    }
                }
            }
        }

        return $variables;
    }

    /**
     * Is the request a multipart-request?
     *
     * @return bool
     */
    protected function isMultipartRequest(): bool
    {
        return Str::startsWith(
            $this->request->header('Content-Type'),
            'multipart/form-data'
        );
    }
}

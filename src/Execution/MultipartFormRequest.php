<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use GraphQL\Error\InvariantViolation;

class MultipartFormRequest extends BaseRequest
{
    /**
     * One or more operations, consisting of query, variables and operationName.
     *
     * https://github.com/jaydenseric/graphql-multipart-request-spec#single-file
     *
     * @var mixed[]
     */
    protected $operations;

    /**
     * MultipartFormRequest constructor.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request)
    {
        if (! $request->has('map')) {
            throw new InvariantViolation(
                'Could not find a valid map, be sure to conform to GraphQL multipart request specification: https://github.com/jaydenseric/graphql-multipart-request-spec'
            );
        }

        $this->operations = json_decode(
            $request->input('operations'),
            true
        );

        // If operations is 0-indexed, we assume we are resolving a batched query
        if (isset($this->operations[0])) {
            $this->batchIndex = 0;
        }

        $map = json_decode($request->input('map'), true);

        /**
         * @var string
         * @var array $operationsPaths
         */
        foreach ($map as $fileKey => $operationsPaths) {
            $file = $request->file($fileKey);

            /** @var string $operationsPath */
            foreach ($operationsPaths as $operationsPath) {
                Arr::set($this->operations, $operationsPath, $file);
            }
        }
    }

    /**
     * Get the given variables for the query.
     *
     * @return mixed[]
     */
    public function variables(): array
    {
        return $this->fieldValue('variables') ?? [];
    }

    /**
     * If we are dealing with a batched request, this gets the
     * contents of the currently resolving batch index.
     *
     * @param  string  $key
     * @return array|string|null
     */
    protected function fieldValue(string $key)
    {
        return $this->isBatched()
            ? Arr::get($this->operations, $this->batchIndex.'.'.$key)
            : $this->operations[$key] ?? null;
    }

    /**
     * Are there more batched queries to process?
     *
     * @return bool
     */
    protected function hasMoreBatches(): bool
    {
        return count($this->operations) - 1 > $this->batchIndex;
    }
}

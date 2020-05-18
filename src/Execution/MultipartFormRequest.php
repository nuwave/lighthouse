<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Error\InvariantViolation;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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

        /** @var array<string, array<int, string>> $map */
        $map = json_decode($request->input('map'), true);

        foreach ($map as $fileKey => $operationsPaths) {
            $file = $request->file($fileKey);

            foreach ($operationsPaths as $operationsPath) {
                Arr::set($this->operations, $operationsPath, $file);
            }
        }
    }

    public function variables(): array
    {
        return $this->fieldValue('variables') ?? [];
    }

    protected function fieldValue(string $key)
    {
        return $this->isBatched()
            ? Arr::get($this->operations, $this->batchIndex.'.'.$key)
            : $this->operations[$key] ?? null;
    }

    /**
     * Are there more batched queries to process?
     */
    protected function hasMoreBatches(): bool
    {
        return count($this->operations) - 1 > $this->batchIndex;
    }
}

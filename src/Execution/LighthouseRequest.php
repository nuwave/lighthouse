<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Http\Request;

class LighthouseRequest extends BaseRequest
{
    /**
     * The incoming HTTP request.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * LighthouseRequest constructor.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;

        // If the request has neither a query, nor an operationName,
        // we assume we are resolving a batched query.
        if (! $request->hasAny('query', 'operationName')) {
            $this->batchIndex = 0;
        }
    }

    /**
     * Get the given variables for the query.
     *
     * @return mixed[]
     */
    public function variables(): array
    {
        $variables = $this->fieldValue('variables');

        // In case we are resolving a GET request, variables
        // are sent as a JSON encoded string
        if (is_string($variables)) {
            return json_decode($variables, true) ?? [];
        }

        // If this is a POST request, Laravel already decoded the input for us
        return $variables ?? [];
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
     * Get the contents of a field by key.
     *
     * This is expected to take batched requests into consideration.
     *
     * @param  string  $key
     * @return array|string|null
     */
    protected function fieldValue(string $key)
    {
        return $this->request->input($key)
            ?? $this->request->input("{$this->batchIndex}.{$key}");
    }
}

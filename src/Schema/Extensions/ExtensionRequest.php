<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Illuminate\Http\Request;

class ExtensionRequest
{
    /** @var Request */
    protected $request;

    /** @var bool */
    protected $batched;

    /**
     * @param Request $request
     * @param bool    $batched
     */
    public function __construct(Request $request, $batched = false)
    {
        $this->request = $request;
        $this->batched = $batched;
    }

    /**
     * Get request instance.
     *
     * @return Request
     */
    public function request(): Request
    {
        return $this->request;
    }

    /**
     * Get GraphQL query string.
     *
     * @param int|null $index
     *
     * @return string
     */
    public function queryString($index = null): string
    {
        return is_null($index)
            ? $this->request->input('query', '')
            : array_get($this->request, "{$index}.query");
    }

    /**
     * Get request variables.
     *
     * @param int|null $index
     *
     * @return array|null
     */
    public function variables($index = null)
    {
        $variables = is_null($index)
            ? $this->request->input('variables')
            : array_get($this->request, "{$index}.variables");

        return is_string($variables) ? json_decode($variables, true) : $variables;
    }

    /**
     * Check if request is batched.
     *
     * @return bool
     */
    public function isBatchedRequest(): bool
    {
        return $this->batched;
    }
}

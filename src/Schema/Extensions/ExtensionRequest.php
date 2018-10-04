<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Illuminate\Http\Request;

class ExtensionRequest
{
    /** @var Request */
    protected $request;
    /** @var string */
    protected $queryString;
    /** @var array|null */
    protected $variables;
    /**
     * @param Request $request
     * @param string $queryString
     * @param array $variables
     */
    public function __construct(Request $request, string $queryString, array $variables = null)
    {
        $this->request = $request;
        $this->queryString = $queryString;
        $this->variables = $variables;
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
     * @return string
     */
    public function queryString(): string
    {
        return $this->queryString;
    }

    /**
     * Get request variables.
     *
     * @return array|null
     */
    public function variables()
    {
        return $this->variables;
    }
}

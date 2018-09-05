<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Illuminate\Http\Request;

class ExtensionRequest
{
    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * @var string
     */
    protected $queryString;

    /**
     * @var string
     */
    protected $operationName;

    /**
     * @var array
     */
    protected $variables;

    /**
     * Create instance of extension request.
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->request = array_get($options, 'request');
        $this->queryString = array_get($options, 'queryString');
        $this->operationName = array_get($options, 'operationName');
        $this->variables = array_get($options, 'variables');
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
     * Get request operation name.
     *
     * @return string
     */
    public function operationName(): string
    {
        return $this->operationName;
    }

    /**
     * Get request variables.
     *
     * @return array
     */
    public function variables(): array
    {
        return $this->variables;
    }
}

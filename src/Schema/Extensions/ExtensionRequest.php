<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

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
        $this->queryString = array_get($options, 'query_string');
        $this->operationName = array_get($options, 'operationName');
        $this->variables = array_get($options, 'variables');
    }

    /**
     * Get request instance.
     *
     * @return \Illuminate\Http\Request
     */
    public function request()
    {
        return $this->request;
    }

    /**
     * Get GraphQL query string.
     *
     * @return string
     */
    public function queryString()
    {
        return $this->queryString;
    }

    /**
     * Get request operation name.
     *
     * @return string
     */
    public function operationName()
    {
        return $this->operationName;
    }

    /**
     * Get request variables.
     *
     * @return array
     */
    public function variables()
    {
        return $this->variables;
    }

    /**
     * GraphQL schema.
     *
     * @return \GraphQL\Type\Schema
     */
    public function schema()
    {
        return $this->schema;
    }
}

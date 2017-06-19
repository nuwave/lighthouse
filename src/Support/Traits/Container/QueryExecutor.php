<?php

namespace Nuwave\Lighthouse\Support\Traits\Container;

use GraphQL\GraphQL;
use GraphQL\Error\Error;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;

trait QueryExecutor
{
    /**
     * Graphql requested query.
     *
     * @var string
     */
    protected $query;

    /**
     * Execute GraphQL query.
     *
     * @param  string $query
     * @param  array $variables
     * @param  mixed $rootValue
     * @return array
     */
    public function execute($query, $context = null, $variables = [], $rootValue = null)
    {
        $result = $this->queryAndReturnResult($query, $context, $variables, $rootValue);

        if (! empty($result->errors)) {
            return [
                'data' => $result->data,
                'errors' => array_map([$this, 'formatError'], $result->errors),
            ];
        }

        return ['data' => $result->data];
    }

    /**
     * Execute GraphQL query.
     *
     * @param  string $query
     * @param  array $variables
     * @param  mixed $rootValue
     * @return array
     */
    public function queryAndReturnResult($query, $context = null, $variables = [], $rootValue = null)
    {
        return GraphQL::executeAndReturnResult($this->buildSchema(), $query, $rootValue, $context, $variables);
    }

    /**
     * Format error for output.
     *
     * @param  Error  $e
     * @return array
     */
    public function formatError(Error $e)
    {
        $error = ['message' => $e->getMessage()];
        $locations = $e->getLocations();

        if (! empty($locations)) {
            $error['locations'] = array_map(function ($location) {
                return $location->toArray();
            }, $locations);
        }

        $previous = $e->getPrevious();

        if ($previous && $previous instanceof ValidationError) {
            $error['validation'] = $previous->getValidatorMessages();
        }

        return $error;
    }

    /**
     * Get current graphql query.
     *
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Set current graphql query.
     *
     * @param string $query
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }
}

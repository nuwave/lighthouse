<?php

namespace Nuwave\Relay\Support\Traits\Container;

use GraphQL\Error;
use GraphQL\GraphQL;
use Nuwave\Relay\Support\Exceptions\ValidationError;

trait QueryExecutor
{
    /**
     * Execute GraphQL query.
     *
     * @param  string $query
     * @param  array $variables
     * @param  mixed $rootValue
     * @return array
     */
    public function execute($query, $variables = [], $rootValue = null)
    {
        $result = $this->queryAndReturnResult($query, $variables, $rootValue);

        if (!empty($result->errors)) {
            return [
                'data' => $result->data,
                'errors' => array_map([$this, 'formatError'], $result->errors)
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
    public function queryAndReturnResult($query, $variables = [], $rootValue = null)
    {
        return GraphQL::executeAndReturnResult($this->schema(), $query, $rootValue, $variables);
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

        if (!empty($locations)) {
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
}

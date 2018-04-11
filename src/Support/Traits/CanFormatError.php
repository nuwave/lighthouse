<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;

trait CanFormatError
{
    /**
     * Error handler.
     *
     * @var \Closure
     */
    protected $errorHandler;

    /**
     * Set GraphQL error handler.
     *
     * @param \Closure $handler
     */
    public function error($handler)
    {
        $this->errorHandler = $handler;
    }

    /**
     * Format error for output.
     *
     * @param Error $e
     *
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

        if ($this->errorHandler && is_callable($this->errorHandler)) {
            $handler = $this->errorHandler;

            return $handler($error);
        }

        return $error;
    }
}

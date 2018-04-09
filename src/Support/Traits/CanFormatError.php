<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;

trait CanFormatError
{
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

        return $error;
    }
}

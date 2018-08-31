<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Error\FormattedError;
use Nuwave\Lighthouse\Support\Contracts\ErrorFormatter;
use Nuwave\Lighthouse\Exceptions\ValidationException;

class ExceptionFormatter implements ErrorFormatter
{
    /**
     * A function that can be set as an Error Handler on GraphQL\Executor\ExecutionResult->setErrorHandler()
     *
     * @param \Throwable $throwable
     *
     * @throws \Throwable
     *
     * @return array
     */
    public static function format(\Throwable $throwable): array
    {
        $formatted = FormattedError::createFromException($throwable);

        $underlyingException = $throwable->getPrevious();

        if ($underlyingException instanceof ValidationException) {
            // Format validation messages
            $formatted['extensions'] = [
                'validation' => $underlyingException->errors()
            ];
        }

        return $formatted;
    }
}

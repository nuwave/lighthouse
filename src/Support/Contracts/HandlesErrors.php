<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface HandlesErrors
{
    /**
     * A function that can be set as an Error Handler on GraphQL\Executor\ExecutionResult->setErrorHandler()
     *
     * @param array $errors
     * @param callable $formatter
     * @return array
     */
    public static function handle(array $errors, callable $formatter) : array;
}

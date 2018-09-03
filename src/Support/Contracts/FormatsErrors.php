<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface FormatsErrors
{
    /**
     * A function that can be set as an Error Handler on GraphQL\Executor\ExecutionResult->setErrorHandler()
     *
     * @param \Throwable $throwable
     * @return array
     */
    public static function format(\Throwable $throwable) : array;
}

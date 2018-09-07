<?php

namespace Nuwave\Lighthouse\Support\Exceptions;

use Illuminate\Support\Arr;
use GraphQL\Error\FormattedError;
use GraphQL\Error\Error as GraphError;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\Errorable;
use Nuwave\Lighthouse\Support\Contracts\ExceptionHandler;

class Handler implements ExceptionHandler
{
    /**
     * @param array|GraphError[] $errors
     *
     * @throws \Throwable
     *
     * @return array
     */
    public function handler(array $errors) : array
    {
        $response = [];
        foreach ($errors as $error) {
            if(!is_null($error->getPrevious()) && $error->getPrevious() instanceof \Exception) {
                $this->report($error->getPrevious());
                $error = $this->render($error->getPrevious());

                if($error instanceof NestedError) {
                    $response = array_merge($response, $error->toArray());
                }
                else {
                    $response[] = $error->toArray();
                }
            }
            else {
                $this->report($error);
                $response[] = $this->render($error)->toArray();
            }
        }
        return $response;
    }

    /**
     * @param \Exception $exception
     */
    public function report(\Exception $exception)
    {
        // throw $exception;
        info('GraphQL Error:', [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * @param \Exception $exception
     *
     * @throws \Throwable
     *
     * @return Error
     */
    public function render(\Exception $exception) : Error
    {
        if($exception instanceof Errorable) {
            return $exception->toError();
        }

        if($exception instanceof ValidationException) {
            return NestedError::fromValidationException($exception);
        }

        if($exception instanceof GraphError) {
            return Error::fromArray(FormattedError::createFromException($exception));
        }

        // Printing random error instead of message as we don't want to leak sensitive application info.
        return $this->convertExceptionToError($exception);
    }


    /**
     * Convert the given exception to an array.
     *
     * @param  \Exception  $e
     * @return Error
     */
    protected function convertExceptionToError(\Exception $e) : Error
    {
        return config('app.debug')
            ? Error::fromArray([
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->map(function ($trace) {
                    return Arr::except($trace, ['args']);
                })->all(),
            ])
            : Error::default();
    }
}

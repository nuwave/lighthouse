<?php


namespace Nuwave\Lighthouse\Support\Exceptions;

use Exception;
use GraphQL\Error\Error as GraphError;
use GraphQL\Error\FormattedError;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\Errorable;

class Handler
{
    /**
     * @param array|GraphError[] $errors
     * @return array
     */
    public function handler(array $errors) : array
    {
        $response = [];
        foreach ($errors as $error) {
            if(!is_null($error->getPrevious()) && $error->getPrevious() instanceof Exception) {
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

    public function report(Exception $exception)
    {
        //throw $exception;
        info('GraphQL Error:', [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    public function render(Exception $exception) : Error
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
        return Error::default();
    }
}
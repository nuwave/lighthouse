<?php

namespace Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;

class ThrowingExceptionHandler implements ExceptionHandler
{
    public function report(\Throwable $e)
    {
    }

    public function render($request, \Throwable $e)
    {
        throw $e;
    }

    public function renderForConsole($output, \Throwable $e)
    {
        throw $e;
    }

    public function shouldReport(\Throwable $e)
    {
        return false;
    }
}

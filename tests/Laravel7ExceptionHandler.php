<?php

namespace Tests;

use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

class Laravel7ExceptionHandler implements ExceptionHandler
{
    public function report(Throwable $e)
    {
        //
    }

    public function render($request, Throwable $e)
    {
        throw $e;
    }

    public function renderForConsole($output, Exception $e)
    {
        //
    }

    public function shouldReport(Exception $e)
    {
        return false;
    }
}

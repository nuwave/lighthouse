<?php

namespace Tests;

use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;

class PreLaravel7ExceptionHandler implements ExceptionHandler
{
    public function report(Exception $e)
    {
        //
    }

    public function render($request, Exception $e)
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

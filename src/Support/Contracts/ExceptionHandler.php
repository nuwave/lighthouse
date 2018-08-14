<?php


namespace Nuwave\Lighthouse\Support\Contracts;


interface ExceptionHandler
{
    public function handler(array $errors) : array;
}

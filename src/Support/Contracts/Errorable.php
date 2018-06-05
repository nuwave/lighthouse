<?php


namespace Nuwave\Lighthouse\Support\Contracts;




use Nuwave\Lighthouse\Support\Exceptions\Error;

interface Errorable
{
    public function toError() : Error;
}
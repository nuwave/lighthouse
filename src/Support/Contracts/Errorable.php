<?php


namespace Nuwave\Lighthouse\Support\Contracts;


use GraphQL\Error\Error;

interface Errorable
{
    public function toError() : Error;
}
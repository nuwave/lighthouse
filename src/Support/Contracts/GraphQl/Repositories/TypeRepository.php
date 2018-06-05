<?php


namespace Nuwave\Lighthouse\Support\Contracts\GraphQl\Repositories;


use Closure;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Type;

interface TypeRepository
{
    public function fromDriver($type) : Type;

    public function create(string $type, string $name, Closure $fields, Closure $resolve = null, string $description = null) : Type;
}
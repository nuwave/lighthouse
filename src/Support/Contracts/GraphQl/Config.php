<?php


namespace Nuwave\Lighthouse\Support\Contracts\GraphQl;


use Closure;
use Illuminate\Support\Collection;

interface Config
{
    /**
     * @param Closure|null $fields
     * @return Closure|Config
     */
    public function fields(Closure $fields = null);
}
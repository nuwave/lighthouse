<?php


namespace Nuwave\Lighthouse\Support\Contracts\Directives;


use Closure;
use Nuwave\Lighthouse\Schema\ManipulatorInfo;

interface FieldManipulator extends ManipulatorDirective
{
    public function manipulateField(ManipulatorInfo $info, Closure $next);
}
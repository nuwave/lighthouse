<?php


namespace Nuwave\Lighthouse\Support\Contracts\Directives;


use Closure;
use Nuwave\Lighthouse\Schema\ManipulatorInfo;

interface ArgumentManipulator extends ManipulatorDirective
{
    public function manipulateArgument(ManipulatorInfo $info, Closure $next);
}
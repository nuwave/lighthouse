<?php


namespace Nuwave\Lighthouse\Support\Contracts\Directives;


use Closure;
use Nuwave\Lighthouse\Schema\ManipulatorInfo;

interface NodeManipulator extends ManipulatorDirective
{
    public function manipulateNode(ManipulatorInfo $info, Closure $next);
}
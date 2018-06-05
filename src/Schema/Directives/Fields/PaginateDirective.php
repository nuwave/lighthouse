<?php


namespace Nuwave\Lighthouse\Schema\Directives\Fields;


use Closure;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\Directives\FieldDirective;

class PaginateDirective implements FieldDirective
{

    public function name()
    {
        return 'paginate';
    }

    public function handleField(ResolveInfo $resolveInfo, Closure $next)
    {
        dd("here");
        dd($resolveInfo->field()->directive($this->name()));
    }
}
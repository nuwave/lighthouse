<?php


namespace Nuwave\Lighthouse\Schema\Directives\Fields;


use Closure;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\Directives\FieldDirective as FieldDirectiveInterface;
use Nuwave\Lighthouse\Support\Exceptions\InvalidArgsException;

class FieldDirective implements FieldDirectiveInterface
{

    public function name()
    {
        return "field";
    }

    public function handleField(ResolveInfo $resolveInfo, Closure $next)
    {
        $resolver = optional($resolveInfo->field()->directive($this->name())->argument('resolver'))->defaultValue();

        if(!is_null($resolver)) {
            $resolver = Str::parseCallback($resolver);
        } else {
            $resolver[0] = optional($resolveInfo->field()->directive($this->name())->argument('class'))->defaultValue();
            $resolver[1] = optional($resolveInfo->field()->directive($this->name())->argument('method'))->defaultValue();
        }

        // Check if method and class for the resolver is set.
        if(sizeof($resolver) != 2 || empty($resolver[0]) || empty($resolver[1]))
        {
            throw new InvalidArgsException("Directive [{$this->name()}] has invalid args.");
        }

        $resolver[0] = app($resolver[0]);

        return $next(call_user_func_array($resolver, [$resolveInfo]));
    }
}

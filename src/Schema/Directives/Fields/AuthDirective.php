<?php


namespace Nuwave\Lighthouse\Schema\Directives\Fields;


use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\Directives\FieldDirective;

class AuthDirective implements FieldDirective
{
    protected $authFactory;

    /**
     * AuthDirective constructor.
     *
     * @param $authFactory
     */
    public function __construct(AuthFactory $authFactory)
    {
        $this->authFactory = $authFactory;
    }

    public function name()
    {
        return 'auth';
    }

    public function handleField(ResolveInfo $resolveInfo, Closure $next)
    {
        $arg = $resolveInfo->field()->directive($this->name())->argument('guard');
        $guard = optional($arg)->defaultValue();

        $resolveInfo->result($this->authFactory->guard($guard)->user());

        return $next($resolveInfo);
    }
}
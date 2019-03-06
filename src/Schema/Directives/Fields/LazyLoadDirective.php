<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class LazyLoadDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name():string
    {
        return 'lazyLoad';
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $value
     * @param  \Closure  $next
     *
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $value, Closure $next): FieldValue
    {
        $relations = $this->directiveArgValue('relations', []);
        $resolver = $value->getResolver();

        return $next($value->setResolver(function () use ($resolver, $relations) {
            $result = call_user_func_array($resolver, func_get_args());
            ($result instanceof Deferred)
                ? $result->then(function (Collection &$items) use ($relations) {
                    $items->load($relations);

                    return $items;
                })
                : $result->load($relations);

            return $result;
        }));
    }
}

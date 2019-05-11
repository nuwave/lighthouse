<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
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
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $relations = $this->directiveArgValue('relations', []);
        $resolver = $fieldValue->getResolver();

        return $next($fieldValue->setResolver(function () use ($resolver, $relations) {
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

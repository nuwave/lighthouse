<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Extensions\TraceExtension;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class TraceDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'trace';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     * @param \Closure   $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next)
    {
        $value = $next($value);
        $resolver = $value->getResolver();

        return $value->setResolver(function ($root, $args, $context, ResolveInfo $info) use ($resolver) {
            $start = now();
            $result = call_user_func($resolver, $root, $args, $context, $info);

            ($result instanceof \GraphQL\Deferred)
                ? $result->then(function (&$items) use ($info, $start) {
                    app(TraceExtension::class)->record($info, $start);
                })
                : app(TraceExtension::class)->record($info, $start);

            return $result;
        });
    }
}

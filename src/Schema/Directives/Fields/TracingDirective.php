<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Schema\Extensions\TracingExtension;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class TracingDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'tracing';
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
            /** @var ExtensionRegistry $extensionRegistry */
            $extensionRegistry = resolve(ExtensionRegistry::class);
            /** @var TracingExtension $tracingExtension */
            $tracingExtension = $extensionRegistry->get(TracingExtension::name());

            $start = now();
            $result = $resolver($root, $args, $context, $info);

            ($result instanceof \GraphQL\Deferred)
                ? $result->then(function (&$items) use ($info, $start, $tracingExtension) {
                    $tracingExtension->record($info, $start, now());
                })
                : $tracingExtension->record($info, $start, now());

            return $result;
        });
    }
}

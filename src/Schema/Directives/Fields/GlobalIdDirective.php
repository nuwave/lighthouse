<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class GlobalIdDirective implements FieldMiddleware
{
    use HandlesDirectives, HandlesGlobalId;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'globalId';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    public function handle(FieldValue $value)
    {
        $type = $value->getNodeName();
        $resolver = $value->getResolver();
        $process = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), 'globalId'),
            'process',
            'encode'
        );

        return $value->setResolver(function () use ($resolver, $process, $type) {
            $args = func_get_args();
            $value = call_user_func_array($resolver, $args);

            return 'encode' === $process
                ? $this->encodeGlobalId($type, $value)
                : $this->decodeRelayId($value);
        });
    }
}

<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class GlobalIdDirective extends AbstractFieldDirective implements FieldMiddleware
{
    use HandlesGlobalId;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
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
    public function handleField(FieldValue $value)
    {
        $resolver = $value->getResolver();
        $process = $this->associatedArgValue('process', 'encode');
        $parentTypeName = $value->getParentTypeName();

        return $value->setResolver(function () use ($resolver, $process, $parentTypeName) {
            $args = func_get_args();
            $value = call_user_func_array($resolver, $args);

            return 'encode' === $process
                ? $this->encodeGlobalId($parentTypeName, $value)
                : $this->decodeRelayId($value);
        });
    }
}

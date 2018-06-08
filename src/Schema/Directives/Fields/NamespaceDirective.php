<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class NamespaceDirective implements FieldMiddleware
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'namespace';
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
        $namespace = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), self::name()),
            'value'
        );

        if (is_string($namespace)) {
            $value->setNamespace($namespace);
        }

        return $value;
    }
}

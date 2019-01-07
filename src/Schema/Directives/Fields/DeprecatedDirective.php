<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class DeprecatedDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'deprecated';
    }

    /**
     * Resolve the field directive.
     *
     * @param  FieldValue $value
     * @param  \Closure   $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next)
    {
        $reason = $this->directiveArgValue('reason');

        if (! $reason) {
            $parentName = $value->getParentName();
            $fieldName = $value->getFieldName();
            $message = "The @{$this->name()} directive requires a `reason` argument [defined on {$parentName}]";

            throw new DirectiveException($message);
        }

        $value->setDeprecationReason($reason);

        return $next($value);
    }
}

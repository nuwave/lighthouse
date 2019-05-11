<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class DeprecatedDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'deprecated';
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $reason = $this->directiveArgValue('reason');

        if (! $reason) {
            throw new DirectiveException(
                "The @{$this->name()} directive requires a `reason` argument [defined on {$fieldValue->getParentName()}]"
            );
        }

        $fieldValue->setDeprecationReason($reason);

        return $next($fieldValue);
    }
}

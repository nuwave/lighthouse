<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class ComplexityDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'complexity';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     * @param \Closure $next
     *
     * @throws DirectiveException
     * @throws DefinitionException
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next): FieldValue
    {
        return $next(
            $value->setComplexity(
                $this->directiveHasArgument('resolver')
                    ? $this->getResolverFromArgument('resolver')
                    : function ($childrenComplexity, $args) {
                        $complexity = array_get($args, 'first', array_get($args, 'count', 1));

                        return $childrenComplexity * $complexity;
                    }
            )
        );
    }
}

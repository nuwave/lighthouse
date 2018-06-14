<?php

namespace Nuwave\Lighthouse\Schema\Directives\Types;

use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class SecurityDirective implements TypeMiddleware
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'security';
    }

    /**
     * Handle node value.
     *
     * @param TypeValue $typeValue
     *
     * @throws DirectiveException
     *
     * @return TypeValue
     */
    public function handleNode(TypeValue $typeValue)
    {
        if ('Query' !== $typeValue->getName()) {
            $message = sprintf(
                'The `%s` directive can only be placed on the %s type [%s]',
                self::name(),
                'Query',
                $typeValue->getName()
            );

            throw new DirectiveException($message);
        }

        $this->queryDepth($typeValue);
        $this->queryComplexity($typeValue);
        $this->queryIntrospection($typeValue);

        return $typeValue;
    }

    /**
     * Set the max query complexity.
     *
     * @param TypeValue $value
     */
    protected function queryComplexity(TypeValue $value)
    {
        $complexity = $this->directiveArgValue(
            $this->nodeDirective($value->getDefinition(), self::name()),
            'complexity'
        );

        if ($complexity) {
            DocumentValidator::addRule(
                new QueryComplexity($complexity)
            );
        }
    }

    /**
     * Set max query depth.
     *
     * @param TypeValue $value
     */
    protected function queryDepth(TypeValue $value)
    {
        $depth = $this->directiveArgValue(
            $this->nodeDirective($value->getDefinition(), self::name()),
            'depth'
        );

        if ($depth) {
            DocumentValidator::addRule(
                new QueryDepth($depth)
            );
        }
    }

    /**
     * Set introspection rule.
     *
     * @param TypeValue $value
     */
    protected function queryIntrospection(TypeValue $value)
    {
        $introspection = $this->directiveArgValue(
            $this->nodeDirective($value->getDefinition(), self::name()),
            'introspection'
        );

        if (false === $introspection) {
            DocumentValidator::addRule(new DisableIntrospection());
        }
    }
}

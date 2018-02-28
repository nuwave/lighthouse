<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class FieldDirective implements FieldResolver
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'field';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldDefinitionNode $field
     *
     * @return \Closure
     */
    public function handle(FieldDefinitionNode $field)
    {
        $directive = $this->fieldDirective($field, $this->name());
        $className = $this->getClassName($directive);
        $method = $this->getMethod($directive);
        $instance = app($className);

        return (new \ReflectionClass($instance))
            ->getMethod($method)
            ->getClosure($instance);
    }

    /**
     * Get class name for resolver.
     *
     * @param DirectiveNode $directive
     *
     * @return string
     */
    protected function getClassName(DirectiveNode $directive)
    {
        $class = $this->directiveArgValue($directive, 'class');

        if (! $class) {
            throw new DirectiveException(sprintf(
                'Directive [%s] must have a `class` argument.',
                $directive->name->value
            ));
        }

        return $class;
    }

    /**
     * Get method for resolver.
     *
     * @param DirectiveNode $directive
     *
     * @return string
     */
    protected function getMethod(DirectiveNode $directive)
    {
        $method = $this->directiveArgValue($directive, 'method');

        if (! $method) {
            throw new DirectiveException(sprintf(
                'Directive [%s] must have a `method` argument.',
                $directive->name->value
            ));
        }

        return $method;
    }
}

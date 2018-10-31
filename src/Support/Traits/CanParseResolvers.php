<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\Directives\Fields\NamespaceDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

/**
 * @deprecated This trait will be removed in a future version of Lighthouse.
 */
trait CanParseResolvers
{
    use HandlesDirectives;

    /**
     * Get resolver namespace.
     *
     * @param FieldValue    $value
     * @param DirectiveNode $directive\
     * @param bool          $throw
     *
     * @return string
     */
    protected function getResolver(FieldValue $value, DirectiveNode $directive, $throw = true)
    {
        if ($resolver = $this->directiveArgValue($directive, 'resolver')) {
            $className = array_get(explode('@', $resolver), '0');
            $namespace = $this->associatedNamespace($value->getField());

            return $namespace ? $namespace . '\\' . $className : $className;
        }

        return $value
            ->getParent()
            ->getNamespace(
                $this->getResolverClassName($directive, $throw)
            );
    }

    /**
     * Get the namespace for this field, returns an empty string if its not set.
     *
     * @param \GraphQL\Language\AST\FieldDefinitionNode $fieldDefinition
     *
     * @return string
     */
    protected function associatedNamespace($fieldDefinition)
    {
        $namespaceDirective = $this->fieldDirective(
            $fieldDefinition,
            (new NamespaceDirective)->name()
        );

        return $namespaceDirective
        // Look if a namespace for the current field is set, if not default to an empty string
         ? $this->directiveArgValue($namespaceDirective, $this->name(), '')
        // Default to an empty namespace if the namespace directive does not exist
         : '';
    }

    /**
     * Get class name for resolver.
     *
     * @param DirectiveNode $directive
     * @param bool          $throw
     *
     * @return string
     */
    protected function getResolverClassName(DirectiveNode $directive, $throw = true)
    {
        $class = $this->directiveArgValue($directive, 'class');

        if (!$class && $throw) {
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
    protected function getResolverMethod(DirectiveNode $directive)
    {
        if ($resolver = $this->directiveArgValue($directive, 'resolver')) {
            if ($method = array_get(explode('@', $resolver), '1')) {
                return $method;
            }
        }

        $method = $this->directiveArgValue($directive, 'method');

        if (!$method) {
            throw new DirectiveException(sprintf(
                'Directive [%s] must have a `method` argument.',
                $directive->name->value
            ));
        }

        return $method;
    }
}

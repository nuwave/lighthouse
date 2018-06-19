<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

abstract class BaseFieldDirective implements Directive
{
    use HandlesDirectives;

    /**
     * Original field definition.
     *
     * @var FieldDefinitionNode
     */
    protected $fieldDefinition;

    /**
     * The hydrate function is called when retrieving a field directive from the directive registry.
     *
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return $this
     */
    public function hydrate(FieldDefinitionNode $fieldDefinition)
    {
        $this->fieldDefinition = $fieldDefinition;

        return $this;
    }

    /**
     * Get the directive definition that belongs to the current directive.
     *
     * @return DirectiveNode
     */
    protected function associatedDirective()
    {
        return $this->fieldDirective($this->fieldDefinition, $this->name());
    }

    /**
     * Get an argument value from the directive definition that belongs to the current directive.
     *
     * @param string     $argName
     * @param mixed|null $default
     *
     * @return mixed
     */
    protected function associatedArgValue($argName, $default = null)
    {
        $directive = $this->associatedDirective($this->fieldDefinition);

        return $this->directiveArgValue($directive, $argName, $default);
    }

    /**
     * Add the namespace to a classname and check if it exists.
     *
     * @param string $baseClassName
     *
     * @throws DirectiveException
     *
     * @return string
     */
    protected function namespaceClassName($baseClassName)
    {
        $className = $this->associatedNamespace() . '\\' . $baseClassName;

        if (!class_exists($className)) {
            $directiveName = $this->name();
            throw new DirectiveException("No class '$className' was found for directive '$directiveName'");
        }

        return $className;
    }

    /**
     * Get the namespace for this field, returns an empty string if its not set.
     *
     * @return string
     */
    protected function associatedNamespace()
    {
        $namespaceDirective = $this->fieldDirective(
            $this->fieldDefinition,
            (new NamespaceDirective)->name()
        );

        return $namespaceDirective
        // Look if a namespace for the current field is set, if not default to an empty string
         ? $this->directiveArgValue($namespaceDirective, $this->name(), '')
        // Default to an empty namespace if the namespace directive does not exist
         : '';
    }
}

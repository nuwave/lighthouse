<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\Fields\NamespaceDirective;

abstract class BaseDirective implements Directive
{
    /**
     * The node the directive is defined on.
     *
     * @var TypeSystemDefinitionNode
     */
    protected $definitionNode;

    /**
     * The hydrate function is called when retrieving a directive from the directive registry.
     *
     * @todo Make this type annotation a hard requirement as soon as the underlying implementation is fixed
     *
     * @param TypeSystemDefinitionNode $definitionNode
     *
     * @return $this
     */
    public function hydrate($definitionNode): BaseDirective
    {
        $this->definitionNode = $definitionNode;

        return $this;
    }

    /**
     * This can be at most one directive, since directives can only be used once per location.
     *
     * @param string|null                   $name
     * @param TypeSystemDefinitionNode|null $definitionNode
     *
     * @return DirectiveNode|null
     */
    protected function directiveDefinition($name = null, $definitionNode = null)
    {
        $name = $name ?? static::name();
        $definitionNode = $definitionNode ?? $this->definitionNode;

        return collect($definitionNode->directives)->first(function (DirectiveNode $directiveDefinitionNode) use ($name) {
            return $directiveDefinitionNode->name->value === $name;
        });
    }

    /**
     * Get directive argument value.
     *
     * @param string             $name
     * @param mixed|null         $default
     * @param DirectiveNode|null $directive
     *
     * @return mixed
     */
    protected function directiveArgValue(string $name, $default = null, $directive = null)
    {
        // Get the definition associated with the class of the directive, unless explicitely given
        $directive = $directive ?? $this->directiveDefinition();

        if (! $directive) {
            return $default;
        }

        $arg = collect($directive->arguments)->first(function (ArgumentNode $argumentNode) use ($name) {
            return $argumentNode->name->value === $name;
        });

        return $arg
            ? $this->argValue($arg, $default)
            : $default;
    }

    /**
     * @throws DirectiveException
     *
     * @return string
     */
    protected function getModelClass(): string
    {
        $model = $this->directiveArgValue('model');

        if (! $model) {
            throw new DirectiveException(
                'A `model` argument must be assigned to the '
                .$this->name().'directive on '.$this->definitionNode->name->value);
        }

        if (! class_exists($model)) {
            $model = config('lighthouse.namespaces.models').'\\'.$model;
        }

        if (! class_exists($model)) {
            $model = $this->namespaceClassName($model);
        }

        return $model;
    }

    /**
     * Add the namespace to a class name and check if it exists.
     *
     * @param string $baseClassName
     *
     * @throws DirectiveException
     *
     * @return string
     */
    protected function namespaceClassName(string $baseClassName): string
    {
        $className = $this->associatedNamespace().'\\'.$baseClassName;

        if (! class_exists($className)) {
            $directiveName = static::name();
            throw new DirectiveException("No class '$className' was found for directive '$directiveName'");
        }

        return $className;
    }

    /**
     * Get the namespace for the current directive, returns an empty string if its not set.
     *
     * @return string
     */
    protected function associatedNamespace(): string
    {
        $namespaceDirective = $this->directiveDefinition(
            (new NamespaceDirective())->name()
        );

        return $namespaceDirective
            // The namespace directive can contain an argument with the name of the
            // current directive, in which case it applies here
            ? $this->directiveArgValue(static::name(), '', $namespaceDirective)
            // Default to an empty namespace if the namespace directive does not exist
            : '';
    }

    /**
     * Get argument's value.
     *
     * @param Node  $arg
     * @param mixed $default
     *
     * @return mixed
     */
    protected function argValue(Node $arg, $default = null)
    {
        $valueNode = $arg->value;

        if (! $valueNode) {
            return $default;
        }

        if ($valueNode instanceof ListValueNode) {
            return collect($valueNode->values)->map(function (ValueNode $valueNode) {
                return $valueNode->value;
            })->toArray();
        }

        if ($valueNode instanceof ObjectValueNode) {
            return collect($valueNode->fields)->mapWithKeys(function (ObjectFieldNode $field) {
                return [$field->name->value => $this->argValue($field)];
            })->toArray();
        }

        return $valueNode->value;
    }
}

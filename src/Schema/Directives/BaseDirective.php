<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
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
     * @return BaseDirective
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

        return collect($definitionNode->directives)
            ->first(function (DirectiveNode $directiveDefinitionNode) use ($name) {
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
        // Get the definition associated with the class of the directive, unless explicitly given
        $directive = $directive ?? $this->directiveDefinition();

        if (! $directive) {
            return $default;
        }

        return ASTHelper::directiveArgValue($directive, $name, $default);
    }
    
    /**
     * Get the resolver that is specified in the current directive.
     *
     * @param \Closure $defaultResolver Add in a default resolver to return if no resolver class is given.
     * @param string $argumentName If the name of the directive argument is not "resolver" you may overwrite it.
     *
     * @throws DirectiveException
     *
     * @return \Closure
     */
    protected function getResolver(\Closure $defaultResolver = null, string $argumentName = 'resolver'): \Closure
    {
        // The resolver is expected to contain a class and a method name, seperated by an @ symbol
        // e.g. App\My\Class@methodName
        $resolverArgumentFragments = explode('@', $this->directiveArgValue($argumentName));

        $baseClassName =
            $this->directiveArgValue('class')
            ?? $resolverArgumentFragments[0];

        if (empty($baseClassName)) {
            // If a default is given, simply return it
            if($defaultResolver){
                return $defaultResolver;
            }
            
            $directiveName = $this->name();
            throw new DirectiveException("Directive '{$directiveName}' must have a resolver class specified.");
        }
        
        $resolverClass = $this->namespaceClassName($baseClassName);
        $resolverMethod =
            $this->directiveArgValue('method')
            ?? $resolverArgumentFragments[1]
            ?? 'resolve';

        if (! method_exists($resolverClass, $resolverMethod)) {
            throw new DirectiveException("Method '{$resolverMethod}' does not exist on class '{$resolverClass}'");
        }

        return \Closure::fromCallable([app($resolverClass), $resolverMethod]);
    }

    /**
     * Get the model class from the `model` argument of the field.
     *
     * @throws DirectiveException
     * @throws \Exception
     *
     * @return string
     */
    protected function getModelClass(): string
    {
        $model = $this->directiveArgValue('model');

        // Fallback to using the return type of the field as the class name
        if(! $model && $this->definitionNode instanceof FieldDefinitionNode){
            $model = ASTHelper::getFieldTypeName($this->definitionNode);
        }

        if (! $model) {
            throw new DirectiveException("A `model` argument must be assigned to the {$this->name()} directive on {$this->definitionNode->name->value}");
        }

        if(class_exists($model)){
            return $model;
        }

        $modelWithDefaultNamespace = config('lighthouse.namespaces.models').'\\'.$model;
        if(class_exists($modelWithDefaultNamespace)){
            return $modelWithDefaultNamespace;
        }

        return $this->namespaceClassName($model);
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
            (new NamespaceDirective)->name()
        );

        return $namespaceDirective
            // The namespace directive can contain an argument with the name of the
            // current directive, in which case it applies here
            ? $this->directiveArgValue(static::name(), '', $namespaceDirective)
            // Default to an empty namespace if the namespace directive does not exist
            : '';
    }
}

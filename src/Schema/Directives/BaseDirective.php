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
     * Get the directive definition associated with the current directive.
     *
     * @return DirectiveNode
     */
    protected function directiveDefinition(): DirectiveNode
    {
        return ASTHelper::directiveDefinition(static::name(), $this->definitionNode);
    }

    /**
     * Get directive argument value.
     *
     * @param string $name
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    protected function directiveArgValue(string $name, $default = null)
    {
        if (! $directive = $this->directiveDefinition()) {
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
        $baseClassName =
            $this->directiveArgValue('class')
            ?? str_before($this->directiveArgValue($argumentName), '@');

        if (empty($baseClassName)) {
            // If a default is given, simply return it
            if($defaultResolver){
                return $defaultResolver;
            }
            
            throw new DirectiveException("Directive '{$this->name()}' must have a resolver class specified.");
        }
        
        $resolverClass = $this->namespaceClassName($baseClassName);
        $resolverMethod =
            $this->directiveArgValue('method')
            ?? str_after($this->directiveArgValue($argumentName), '@')
            ?? 'resolve';

        if (! method_exists($resolverClass, $resolverMethod)) {
            throw new DirectiveException("Method '{$resolverMethod}' does not exist on class '{$resolverClass}'");
        }

        return \Closure::fromCallable([app($resolverClass), $resolverMethod]);
    }

    /**
     * @throws DirectiveException
     * @throws \Exception
     *
     * @return string
     */
    protected function getModelClass(): string
    {
        $model = $this->directiveArgValue('model');

        // Fallback to using the return type of the field
        if(! $model && $this->definitionNode instanceof FieldDefinitionNode){
            $model = ASTHelper::getFieldTypeName($this->definitionNode);
        }

        if (! $model) {
            throw new DirectiveException(
                "A `model` argument must be assigned to the '{$this->name()}'directive on '{$this->definitionNode->name->value}"
            );
        }

        return $this->namespaceClassName($model, [
            config('lighthouse.namespaces.models')
        ]);
    }
    
    /**
     *
     * @param string $classCandidate
     * @param string[] $namespacesToTry
     *
     * @throws DirectiveException
     *
     * @return string
     */
    protected function namespaceClassName(string $classCandidate, array $namespacesToTry = []): string
    {
        // Always try the explicitly set namespace first
        \array_unshift($namespacesToTry, ASTHelper::getNamespaceForDirective($this->definitionNode, static::name()));
        
        if(!$className = \namespace_classname($classCandidate, $namespacesToTry)){
            throw new DirectiveException("No class '$classCandidate' was found for directive '{$this->name()}'");
        };
        
        return $className;
    }
}

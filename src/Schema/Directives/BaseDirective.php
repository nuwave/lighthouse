<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

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
        return ASTHelper::directiveDefinition(
            $this->definitionNode,
            static::name()
        );
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
        return ASTHelper::directiveArgValue(
            $this->directiveDefinition(),
            $name,
            $default
        );
    }

    /**
     * Does the current directive have an argument with the given name?
     *
     * @param string $name
     *
     * @return bool
     */
    public function directiveHasArgument(string $name): bool
    {
        return ASTHelper::directiveHasArgument(
            $this->directiveDefinition(),
            $name
        );
    }

    /**
     * Get a Closure that is defined through an argument on the directive.
     *
     * @param string $argumentName
     *
     * @throws DefinitionException
     * @throws DirectiveException
     *
     * @return \Closure
     */
    public function getResolverFromArgument(string $argumentName): \Closure
    {
        list($className, $methodName) = $this->getMethodArgumentParts($argumentName);

        $namespacedClassName = $this->namespaceClassName($className);

        return \construct_resolver($namespacedClassName, $methodName);
    }

    /**
     * Get the model class from the `model` argument of the field.
     *
     * @param string $argumentName The default argument name "model" may be overwritten.
     *
     * @throws DirectiveException
     * @throws DefinitionException
     *
     * @return string
     */
    protected function getModelClass(string $argumentName = 'model'): string
    {
        $model = $this->directiveArgValue($argumentName);

        // Fallback to using information from the schema definition as the model name
        if(! $model){
            if($this->definitionNode instanceof FieldDefinitionNode) {
                $model = ASTHelper::getFieldTypeName($this->definitionNode);
            } elseif($this->definitionNode instanceof ObjectTypeDefinitionNode) {
                $model = $this->definitionNode->name->value;
            }
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
        \array_unshift(
            $namespacesToTry,
            ASTHelper::getNamespaceForDirective(
                $this->definitionNode,
                static::name()
            )
        );

        if(!$className = \namespace_classname($classCandidate, $namespacesToTry)){
            throw new DirectiveException(
                "No class '$classCandidate' was found for directive '{$this->name()}'"
            );
        };

        return $className;
    }

    /**
     * Split a single method argument into its parts.
     *
     * A method argument is expected to contain a class and a method name, separated by an @ symbol.
     * e.g. "App\My\Class@methodName"
     * This validates that exactly two parts are given and are not empty.
     *
     * @param string $argumentName
     *
     * @throws DirectiveException
     *
     * @return array [string $className, string $methodName]
     */
    protected function getMethodArgumentParts(string $argumentName): array
    {
        $argumentParts = explode(
            '@',
            $this->directiveArgValue($argumentName)
        );

        if (
            count($argumentParts) !== 2
            || empty($argumentParts[0])
            || empty($argumentParts[1])
        ){
            throw new DirectiveException(
                "Directive '{$this->name()}' must have an argument '{$argumentName}' in the form 'ClassName@methodName'"
            );
        }

        return $argumentParts;
    }
}

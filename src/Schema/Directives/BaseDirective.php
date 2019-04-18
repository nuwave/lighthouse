<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Nuwave\Lighthouse\Support\Utils;
use GraphQL\Language\AST\DirectiveNode;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

abstract class BaseDirective implements Directive
{
    /**
     * The node the directive is defined on.
     *
     * @var \GraphQL\Language\AST\TypeSystemDefinitionNode
     */
    protected $definitionNode;

    /**
     * The hydrate function is called when retrieving a directive from the directive registry.
     *
     * @todo Make this type annotation a hard requirement as soon as the underlying implementation is fixed
     *
     * @param  \GraphQL\Language\AST\TypeSystemDefinitionNode  $definitionNode
     * @return $this
     */
    public function hydrate($definitionNode): self
    {
        $this->definitionNode = $definitionNode;

        return $this;
    }

    /**
     * Get the directive definition associated with the current directive.
     *
     * @return \GraphQL\Language\AST\DirectiveNode
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
     * @param  string  $name
     * @param  mixed|null  $default
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
     * @param  string  $name
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
     * @param  string  $argumentName
     * @return \Closure
     */
    public function getResolverFromArgument(string $argumentName): Closure
    {
        [$className, $methodName] = $this->getMethodArgumentParts($argumentName);

        $namespacedClassName = $this->namespaceClassName($className);

        return Utils::constructResolver($namespacedClassName, $methodName);
    }

    /**
     * Get the model class from the `model` argument of the field.
     *
     * @param  string  $argumentName The default argument name "model" may be overwritten
     * @return string
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function getModelClass(string $argumentName = 'model'): string
    {
        $model = $this->directiveArgValue($argumentName);

        // Fallback to using information from the schema definition as the model name
        if (! $model) {
            if ($this->definitionNode instanceof FieldDefinitionNode) {
                $model = ASTHelper::getUnderlyingTypeName($this->definitionNode);
            } elseif ($this->definitionNode instanceof ObjectTypeDefinitionNode) {
                $model = $this->definitionNode->name->value;
            }
        }

        if (! $model) {
            throw new DirectiveException(
                "A `model` argument must be assigned to the '{$this->name()}'directive on '{$this->definitionNode->name->value}"
            );
        }

        return $this->namespaceModelClass($model);
    }

    /**
     * @param  string  $classCandidate
     * @param  string[]  $namespacesToTry
     * @param  callable  $determineMatch
     * @return string
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function namespaceClassName(string $classCandidate, array $namespacesToTry = [], callable $determineMatch = null): string
    {
        // Always try the explicitly set namespace first
        array_unshift(
            $namespacesToTry,
            ASTHelper::getNamespaceForDirective(
                $this->definitionNode,
                static::name()
            )
        );

        if (! $determineMatch) {
            $determineMatch = 'class_exists';
        }

        $className = Utils::namespaceClassname(
            $classCandidate,
            $namespacesToTry,
            $determineMatch
        );

        if (! $className) {
            throw new DirectiveException(
                "No class '{$classCandidate}' was found for directive '{$this->name()}'"
            );
        }

        return $className;
    }

    /**
     * Split a single method argument into its parts.
     *
     * A method argument is expected to contain a class and a method name, separated by an @ symbol.
     * e.g. "App\My\Class@methodName"
     * This validates that exactly two parts are given and are not empty.
     *
     * @param  string  $argumentName
     * @return string[] Contains two entries: [string $className, string $methodName]
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
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
        ) {
            throw new DirectiveException(
                "Directive '{$this->name()}' must have an argument '{$argumentName}' in the form 'ClassName@methodName'"
            );
        }

        return $argumentParts;
    }

    /**
     * Try adding the default model namespace and ensure the given class is a model.
     *
     * @param  string  $modelClassCandidate
     * @return string
     */
    protected function namespaceModelClass(string $modelClassCandidate): string
    {
        return $this->namespaceClassName(
            $modelClassCandidate,
            (array) config('lighthouse.namespaces.models'),
            function (string $classCandidate): bool {
                return is_subclass_of($classCandidate, Model::class);
            }
        );
    }
}

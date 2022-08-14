<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Utils\AST;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Utils;

abstract class BaseDirective implements Directive
{
    /**
     * The AST node of the directive.
     *
     * @var \GraphQL\Language\AST\DirectiveNode
     */
    protected $directiveNode;

    /**
     * The node the directive is defined on.
     *
     * @see \GraphQL\Language\DirectiveLocation
     *
     * Intentionally leaving out the request definitions and the 'SCHEMA' location.
     *
     * @var ScalarTypeDefinitionNode|ObjectTypeDefinitionNode|FieldDefinitionNode|InputValueDefinitionNode|InterfaceTypeDefinitionNode|UnionTypeDefinitionNode|EnumTypeDefinitionNode|EnumValueDefinitionNode|InputObjectTypeDefinitionNode
     */
    protected $definitionNode;

    /**
     * Cached directive arguments.
     *
     * Lazily initialized.
     *
     * @var array<string, mixed>
     */
    protected $directiveArgs;

    /**
     * Returns the name of the used directive.
     */
    public function name(): string
    {
        return $this->directiveNode->name->value;
    }

    /**
     * The hydrate function is called when retrieving a directive from the directive registry.
     *
     * @param  ScalarTypeDefinitionNode|ObjectTypeDefinitionNode|FieldDefinitionNode|InputValueDefinitionNode|InterfaceTypeDefinitionNode|UnionTypeDefinitionNode|EnumTypeDefinitionNode|EnumValueDefinitionNode|InputObjectTypeDefinitionNode  $definitionNode
     */
    public function hydrate(DirectiveNode $directiveNode, Node $definitionNode): self
    {
        $this->directiveNode = $directiveNode;
        $this->definitionNode = $definitionNode;

        return $this;
    }

    /**
     * Get a Closure that is defined through an argument on the directive.
     */
    public function getResolverFromArgument(string $argumentName): Closure
    {
        [$className, $methodName] = $this->getMethodArgumentParts($argumentName);

        $namespacedClassName = $this->namespaceClassName($className);

        return Utils::constructResolver($namespacedClassName, $methodName);
    }

    /**
     * Loads directive argument values from AST and caches them in $directiveArgs.
     */
    protected function loadArgValues(): void
    {
        $this->directiveArgs = [];
        foreach ($this->directiveNode->arguments as $node) {
            if (array_key_exists($node->name->value, $this->directiveArgs)) {
                throw new DefinitionException("Directive {$this->directiveNode->name->value} has two arguments with the same name {$node->name->value}");
            }

            $this->directiveArgs[$node->name->value] = AST::valueFromASTUntyped($node->value);
        }
    }

    /**
     * Does the current directive have an argument with the given name?
     * TODO change to protected in v6.
     */
    public function directiveHasArgument(string $name): bool
    {
        if (! isset($this->directiveArgs)) {
            $this->loadArgValues();
        }

        return array_key_exists($name, $this->directiveArgs);
    }

    /**
     * Get the value of an argument on the directive.
     *
     * @param  mixed  $default Use this over `??` to preserve explicit `null`
     *
     * @return mixed The argument value or the default
     */
    protected function directiveArgValue(string $name, $default = null)
    {
        if (! isset($this->directiveArgs)) {
            $this->loadArgValues();
        }

        return array_key_exists($name, $this->directiveArgs)
            ? $this->directiveArgs[$name]
            : $default;
    }

    /**
     * The name of the node the directive is defined upon.
     */
    protected function nodeName(): string
    {
        return $this->definitionNode->name->value;
    }

    /**
     * Get the model class from the `model` argument of the field.
     *
     * @param  string  $argumentName  The default argument name "model" may be overwritten
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function getModelClass(string $argumentName = 'model'): string
    {
        $model = $this->directiveArgValue($argumentName, ASTHelper::modelName($this->definitionNode));

        if (! $model) {
            throw new DefinitionException("Could not determine a model name for the '@{$this->name()}' directive on '{$this->nodeName()}.");
        }

        return $this->namespaceModelClass($model);
    }

    /**
     * Find a class name in a set of given namespaces.
     *
     * @param  array<string>  $namespacesToTry
     * @param  callable(string $className): bool $determineMatch
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     *
     * @return class-string
     */
    protected function namespaceClassName(
        string $classCandidate,
        array $namespacesToTry = [],
        callable $determineMatch = null
    ): string {
        $namespaceForDirective = ASTHelper::namespaceForDirective(
            $this->definitionNode,
            $this->name()
        );

        if (is_string($namespaceForDirective)) {
            // Always try the explicitly set namespace first
            array_unshift($namespacesToTry, $namespaceForDirective);
        }

        if (! $determineMatch) {
            $determineMatch = 'class_exists';
        }

        $className = Utils::namespaceClassname(
            $classCandidate,
            $namespacesToTry,
            $determineMatch
        );

        if (! $className) {
            $consideredNamespaces = implode(', ', $namespacesToTry);
            throw new DefinitionException(
                "Failed to find class {$classCandidate} in namespaces [{$consideredNamespaces}] for directive @{$this->name()}."
            );
        }

        return $className;
    }

    /**
     * Split a single method argument into its parts.
     *
     * A method argument is expected to contain a class and a method name, separated by an @ symbol.
     *
     * @example "App\My\Class@methodName"
     *
     * This validates that exactly two non-empty parts are given, not that the method exists.
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     *
     * @return array{0: string, 1: string} Contains two entries: [string $className, string $methodName]
     */
    protected function getMethodArgumentParts(string $argumentName): array
    {
        $argumentParts = explode(
            '@',
            $this->directiveArgValue($argumentName)
        );

        if (
            count($argumentParts) > 2
            || empty($argumentParts[0])
        ) {
            throw new DefinitionException(
                "Directive '{$this->name()}' must have an argument '{$argumentName}' in the form 'ClassName@methodName' or 'ClassName'"
            );
        }
        /** @var array{0: string, 1?: string} $argumentParts */
        if (empty($argumentParts[1])) {
            $argumentParts[1] = '__invoke';
        }
        /** @var array{0: string, 1: string} $argumentParts */

        return $argumentParts;
    }

    /**
     * Try adding the default model namespace and ensure the given class is a model.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function namespaceModelClass(string $modelClassCandidate): string
    {
        $modelClass = $this->namespaceClassName(
            $modelClassCandidate,
            (array) config('lighthouse.namespaces.models'),
            static function (string $classCandidate): bool {
                return is_subclass_of($classCandidate, Model::class);
            }
        );
        assert(is_subclass_of($modelClass, Model::class));

        return $modelClass;
    }
}

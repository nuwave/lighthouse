<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Utils;

abstract class BaseDirective implements Directive
{
    /**
     * The node the directive is defined on.
     *
     * @var \GraphQL\Language\AST\Node
     */
    protected $definitionNode;

    /**
     * The hydrate function is called when retrieving a directive from the directive registry.
     *
     * @param  \GraphQL\Language\AST\Node  $definitionNode
     * @return $this
     */
    public function hydrate(Node $definitionNode): self
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
     * @return string|\Illuminate\Database\Eloquent\Model
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function getModelClass(string $argumentName = 'model'): string
    {
        $model = $this->directiveArgValue($argumentName);

        // Fallback to using information from the schema definition as the model name
        if (! $model) {
            if ($this->definitionNode instanceof FieldDefinitionNode) {
                $returnTypeName = ASTHelper::getUnderlyingTypeName($this->definitionNode);

                /** @var \Nuwave\Lighthouse\Schema\AST\DocumentAST $documentAST */
                $documentAST = app(ASTBuilder::class)->documentAST();

                if (! isset($documentAST->types[$returnTypeName])) {
                    throw new DefinitionException(
                        "Type '$returnTypeName' on '{$this->definitionNode->name->value}' can not be found in the schema.'"
                    );
                }
                $type = $documentAST->types[$returnTypeName];

                if ($modelClass = ASTHelper::directiveDefinition($type, 'modelClass')) {
                    $model = ASTHelper::directiveArgValue($modelClass, 'class');
                } else {
                    $model = $returnTypeName;
                }
            } elseif ($this->definitionNode instanceof ObjectTypeDefinitionNode) {
                $model = $this->definitionNode->name->value;
            }
        }

        if (! $model) {
            throw new DefinitionException(
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
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
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
            throw new DefinitionException(
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
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
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

        if (empty($argumentParts[1])) {
            $argumentParts[1] = '__invoke';
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

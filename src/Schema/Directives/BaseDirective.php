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
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
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
     * @return $this
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
     * @param  mixed|null  $default
     * @return mixed|null
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
     * @param  string  $argumentName The default argument name "model" may be overwritten
     * @return class-string<\Illuminate\Database\Eloquent\Model>
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
                        "Type '$returnTypeName' on '{$this->nodeName()}' can not be found in the schema.'"
                    );
                }
                $type = $documentAST->types[$returnTypeName];

                $modelDirective = ASTHelper::directiveDefinition($type, 'model');
                if ($modelDirective !== null) {
                    $model = ASTHelper::directiveArgValue($modelDirective, 'class');
                } else {
                    $model = $returnTypeName;
                }
            } elseif ($this->definitionNode instanceof ObjectTypeDefinitionNode) {
                $model = $this->nodeName();
            }
        }

        if (! $model) {
            throw new DefinitionException(
                "A `model` argument must be assigned to the '{$this->name()}'directive on '{$this->nodeName()}"
            );
        }

        return $this->namespaceModelClass($model);
    }

    /**
     * Find a class name in a set of given namespaces.
     *
     * @param  array<string>  $namespacesToTry
     * @return class-string
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function namespaceClassName(
        string $classCandidate,
        array $namespacesToTry = [],
        callable $determineMatch = null
    ): string {
        // Always try the explicitly set namespace first
        array_unshift(
            $namespacesToTry,
            ASTHelper::getNamespaceForDirective(
                $this->definitionNode,
                $this->name()
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
                "No class `{$classCandidate}` was found for directive `@{$this->name()}`"
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
     * @return array<string> Contains two entries: [string $className, string $methodName]
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
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function namespaceModelClass(string $modelClassCandidate): string
    {
        /**
         * The callback ensures this holds true.
         *
         * @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass
         */
        $modelClass = $this->namespaceClassName(
            $modelClassCandidate,
            (array) config('lighthouse.namespaces.models'),
            static function (string $classCandidate): bool {
                return is_subclass_of($classCandidate, Model::class);
            }
        );

        return $modelClass;
    }
}

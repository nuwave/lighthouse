<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Utils\AST;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Utils;

/**
 * A useful base class for directives.
 *
 * @api
 */
abstract class BaseDirective implements Directive
{
    /**
     * The AST node of the directive.
     *
     * May not be set if the directive is added programmatically.
     */
    public DirectiveNode $directiveNode;

    /**
     * The node the directive is defined on.
     *
     * @see \GraphQL\Language\DirectiveLocation
     *
     * Intentionally leaving out the request definitions and the 'SCHEMA' location.
     */
    public ScalarTypeDefinitionNode|ScalarTypeExtensionNode|ObjectTypeDefinitionNode|ObjectTypeExtensionNode|InterfaceTypeDefinitionNode|InterfaceTypeExtensionNode|UnionTypeDefinitionNode|UnionTypeExtensionNode|EnumTypeDefinitionNode|EnumTypeExtensionNode|InputObjectTypeDefinitionNode|InputObjectTypeExtensionNode|FieldDefinitionNode|InputValueDefinitionNode|EnumValueDefinitionNode $definitionNode;

    /**
     * Cached directive arguments.
     *
     * Lazily initialized.
     *
     * @var array<string, mixed>
     */
    protected array $directiveArgs;

    /** The hydrate function is called when retrieving a directive from the directive registry. */
    public function hydrate(DirectiveNode $directiveNode, ScalarTypeDefinitionNode|ScalarTypeExtensionNode|ObjectTypeDefinitionNode|ObjectTypeExtensionNode|InterfaceTypeDefinitionNode|InterfaceTypeExtensionNode|UnionTypeDefinitionNode|UnionTypeExtensionNode|EnumTypeDefinitionNode|EnumTypeExtensionNode|InputObjectTypeDefinitionNode|InputObjectTypeExtensionNode|FieldDefinitionNode|InputValueDefinitionNode|EnumValueDefinitionNode $definitionNode): self
    {
        $this->directiveNode = $directiveNode;
        $this->definitionNode = $definitionNode;

        unset($this->directiveArgs);

        return $this;
    }

    /**
     * Returns the name of the used directive.
     *
     * @api
     */
    public function name(): string
    {
        return DirectiveLocator::directiveName(static::class);
    }

    /**
     * The name of the node the directive is defined upon.
     *
     * @api
     */
    protected function nodeName(): string
    {
        return $this->definitionNode->name->value;
    }

    /**
     * Get a Closure that is defined through an argument of the directive.
     *
     * @api
     */
    public function getResolverFromArgument(string $argumentName): \Closure
    {
        [$className, $methodName] = $this->getMethodArgumentParts($argumentName);

        $namespacedClassName = $this->namespaceClassName($className);

        return Utils::constructResolver($namespacedClassName, $methodName);
    }

    /**
     * Does the current directive have an argument with the given name?
     *
     * @api
     */
    protected function directiveHasArgument(string $name): bool
    {
        if (! isset($this->directiveArgs)) {
            $this->loadArgValues();
        }

        return array_key_exists($name, $this->directiveArgs);
    }

    /**
     * Get the value of an argument of the directive.
     *
     * @api
     *
     * @param  mixed  $default Use this over `??` to preserve explicit `null`
     *
     * @return mixed The argument value or the default
     */
    protected function directiveArgValue(string $name, mixed $default = null): mixed
    {
        if (! isset($this->directiveArgs)) {
            $this->loadArgValues();
        }

        return array_key_exists($name, $this->directiveArgs)
            ? $this->directiveArgs[$name]
            : $default;
    }

    /**
     * Get the model class from the `model` argument of the field.
     *
     * @api
     *
     * @param  string  $argumentName  The default argument name "model" may be overwritten
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function getModelClass(string $argumentName = 'model'): string
    {
        $model = $this->directiveArgValue($argumentName, ASTHelper::modelName($this->definitionNode))
            ?? throw new DefinitionException("Could not determine a model name for the '@{$this->name()}' directive on '{$this->nodeName()}'.");

        return $this->namespaceModelClass($model);
    }

    /**
     * Find a class name in a set of given namespaces.
     *
     * @api
     *
     * @param  array<string>  $namespacesToTry
     * @param  (callable(string $className): bool)|null  $determineMatch
     *
     * @return class-string
     */
    protected function namespaceClassName(
        string $classCandidate,
        array $namespacesToTry = [],
        ?callable $determineMatch = null,
    ): string {
        $namespaceForDirective = ASTHelper::namespaceForDirective(
            $this->definitionNode,
            $this->name(),
        );

        if (is_string($namespaceForDirective)) {
            // Always try the explicitly set namespace first
            array_unshift($namespacesToTry, $namespaceForDirective);
        }

        if ($determineMatch === null) {
            $determineMatch = 'class_exists';
        }

        $className = Utils::namespaceClassname($classCandidate, $namespacesToTry, $determineMatch);

        if ($className === null) {
            $consideredNamespaces = implode(', ', $namespacesToTry);
            throw new DefinitionException("Failed to find class {$classCandidate} in namespaces [{$consideredNamespaces}] for directive @{$this->name()}.");
        }

        return $className;
    }

    /**
     * Split a single method argument into its parts.
     *
     * @api
     *
     * A method argument is expected to contain a class and a method name, separated by an @ symbol.
     *
     * @example "App\My\Class@methodName"
     *
     * This validates that exactly two non-empty parts are given, not that the method exists.
     *
     * @return array{0: string, 1: string} Contains two entries: [string $className, string $methodName]
     */
    protected function getMethodArgumentParts(string $argumentName): array
    {
        $argumentParts = explode(
            '@',
            $this->directiveArgValue($argumentName),
        );

        if (
            count($argumentParts) > 2
            || empty($argumentParts[0])
        ) {
            throw new DefinitionException(
                "Directive '{$this->name()}' must have an argument '{$argumentName}' in the form 'ClassName@methodName' or 'ClassName'",
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
     * @api
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function namespaceModelClass(string $modelClassCandidate): string
    {
        $modelClass = $this->namespaceClassName(
            $modelClassCandidate,
            (array) config('lighthouse.namespaces.models'),
            static fn (string $classCandidate): bool => is_subclass_of($classCandidate, Model::class),
        );
        assert(is_subclass_of($modelClass, Model::class));

        return $modelClass;
    }

    /**
     * Validate at most one of the given mutually exclusive arguments is used.
     *
     * @api
     *
     * @param  array<string>  $names
     */
    protected function validateMutuallyExclusiveArguments(array $names): void
    {
        $given = array_filter($names, [$this, 'directiveHasArgument']);

        if (count($given) > 1) {
            $namesString = implode(', ', $names);
            $givenString = implode(', ', $given);
            throw new DefinitionException("The arguments [{$namesString}] for @{$this->name()} are mutually exclusive, found [{$givenString}] on {$this->nodeName()}.");
        }
    }

    /** Loads directive argument values from AST and caches them in $directiveArgs. */
    protected function loadArgValues(): void
    {
        $this->directiveArgs = [];

        // If the directive was added programmatically, it has no arguments
        if (! isset($this->directiveNode)) {
            return;
        }

        foreach ($this->directiveNode->arguments as $node) {
            if (array_key_exists($node->name->value, $this->directiveArgs)) {
                throw new DefinitionException("Directive {$this->directiveNode->name->value} has two arguments with the same name {$node->name->value}");
            }

            $this->directiveArgs[$node->name->value] = AST::valueFromASTUntyped($node->value);
        }
    }
}

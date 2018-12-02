<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\Node;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddlewareForArray;
use Nuwave\Lighthouse\Events\RegisteringDirectiveBaseNamespaces;

class DirectiveFactory
{
    /**
     * The already resolved directive classes.
     *
     * @var array
     */
    protected $resolved = [];

    /**
     * The paths used for locating directive class.
     *
     * @var string[]
     */
    protected $directiveBaseNamespaces = [];

    /**
     * DirectiveFactory constructor.
     */
    public function __construct()
    {
        $this->registerDirectiveBaseNamespaces();
    }

    /**
     * Init the `$this->directiveBaseNamespaces`.
     */
    public function registerDirectiveBaseNamespaces()
    {
        $this->directiveBaseNamespaces = collect([
            // User defined directives (top priority)
            config('lighthouse.directive_base_namespaces'),

            // Plugin developers defined directives
            event(new RegisteringDirectiveBaseNamespaces()),

            // Lighthouse defined directives
            'Nuwave\\Lighthouse\\Schema\\Directives\\Args',
            'Nuwave\\Lighthouse\\Schema\\Directives\\Fields',
            'Nuwave\\Lighthouse\\Schema\\Directives\\Nodes',
        ])->flatten()
          ->filter()
          ->all();
    }

    /**
     * Create a directive by the given directive name.
     *
     * @param string                   $directiveName
     * @param TypeSystemDefinitionNode $definitionNode
     *
     * @throws DirectiveException
     *
     * @return Directive
     */
    public function create(string $directiveName, $definitionNode = null): Directive
    {
        $directive = $this->resolve($directiveName)
            ?? $this->createOrFail($directiveName);

        return $definitionNode
            ? $this->hydrate($directive, $definitionNode)
            : $directive;
    }

    /**
     * Create a directive from resolved directive classes.
     *
     * @param string $directiveName
     *
     * @return Directive|null TODO change to `?Directive` when upgraded to PHP 7.1
     */
    protected function resolve(string $directiveName)
    {
        if ($className = data_get($this->resolved, $directiveName)) {
            return resolve($className);
        }

        return null;
    }

    /**
     * @param string $directiveName
     *
     * @throws DirectiveException
     *
     * @return Directive
     */
    protected function createOrFail(string $directiveName): Directive
    {
        foreach ($this->directiveBaseNamespaces as $baseNamespace) {
            $className = $baseNamespace.'\\'.studly_case($directiveName).'Directive';

            if (class_exists($className)) {
                $directive = resolve($className);

                if (! $directive instanceof Directive) {
                    throw new DirectiveException("Class $className is not a directive.");
                }

                $this->addResolved($directiveName, $className);

                return $directive;
            }
        }

        throw new DirectiveException("No directive found for `{$directiveName}`");
    }

    /**
     * @param string $directiveName
     * @param string $className
     *
     * @return static
     */
    public function addResolved(string $directiveName, string $className): self
    {
        if (\in_array($directiveName, $this->resolved, true)) {
            return $this;
        }

        $this->resolved[$directiveName] = $className;

        return $this;
    }

    /**
     * @param string $directiveName
     * @param string $className
     *
     * @return static
     */
    public function setResolved(string $directiveName, string $className): self
    {
        $this->resolved[$directiveName] = $className;

        return $this;
    }

    /**
     * @return static
     */
    public function clearResolved(): self
    {
        $this->resolved = [];

        return $this;
    }

    /**
     * Set the given definition on the directive.
     *
     * @param Directive                $directive
     * @param TypeSystemDefinitionNode $definitionNode
     *
     * @return Directive
     */
    protected function hydrate(Directive $directive, $definitionNode): Directive
    {
        return $directive instanceof BaseDirective
            ? $directive->hydrate($definitionNode)
            : $directive;
    }

    /**
     * Get middleware for field arguments.
     *
     * @param InputValueDefinitionNode $arg
     *
     * @return Collection
     */
    public function createArgMiddleware(InputValueDefinitionNode $arg): Collection
    {
        return $this->associatedDirectivesOfType($arg, ArgMiddleware::class);
    }

    /**
     * Get middleware for field arguments.
     *
     * @param InputValueDefinitionNode $arg
     *
     * @return Collection
     */
    public function createArgMiddlewareForArray(InputValueDefinitionNode $arg): Collection
    {
        return $this->associatedDirectivesOfType($arg, ArgMiddlewareForArray::class);
    }

    /**
     * Get middleware for field arguments.
     *
     * @param InputValueDefinitionNode $arg
     *
     * @return Collection
     */
    public function createArgFilterDirective(InputValueDefinitionNode $arg): Collection
    {
        return $this->associatedDirectivesOfType($arg, ArgFilterDirective::class);
    }

    /**
     * Get all directives of a certain type that are associated with an AST node.
     *
     * @param Node   $node
     * @param string $directiveClass
     *
     * @return Collection
     */
    protected function associatedDirectivesOfType(Node $node, string $directiveClass): Collection
    {
        return collect($node->directives)
            ->map(function (DirectiveNode $directive) use ($node) {
                return $this->create($directive->name->value, $node);
            })
            ->filter(function (Directive $directive) use ($directiveClass) {
                return $directive instanceof $directiveClass;
            });
    }
}

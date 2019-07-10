<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use GraphQL\Language\AST\Node;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\DirectiveNode;
use Illuminate\Contracts\Events\Dispatcher;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class DirectiveFactory
{
    /**
     * A map from short directive names to full class names.
     *
     * E.g.
     * [
     *   'create' => 'Nuwave\Lighthouse\Schema\Directives\CreateDirective',
     *   'custom' => 'App\GraphQL\Directives\CustomDirective',
     * ]
     *
     * @var string[]
     */
    protected $resolvedClassnames = [];

    /**
     * The paths used for locating directive classes.
     *
     * Should be tried in the order they are contained in this array,
     * going from the most significant to least significant.
     *
     * @var string[]
     */
    protected $directiveBaseNamespaces = [];

    /**
     * DirectiveFactory constructor.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */
    public function __construct(Dispatcher $dispatcher)
    {
        // When looking for a directive by name, the namespaces are tried in order
        $this->directiveBaseNamespaces = (new Collection([
            // User defined directives (top priority)
            config('lighthouse.namespaces.directives'),

            // Plugin developers defined directives
            $dispatcher->dispatch(new RegisterDirectiveNamespaces),

            // Lighthouse defined directives
            'Nuwave\\Lighthouse\\Schema\\Directives',
        ]))
            ->flatten()
            ->filter()
            ->all();
    }

    /**
     * Create a directive by the given directive name.
     *
     * @param  string  $directiveName
     * @param  \GraphQL\Language\AST\TypeSystemDefinitionNode|null  $definitionNode
     * @return \Nuwave\Lighthouse\Support\Contracts\Directive
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
     * @param  string  $directiveName
     * @return \Nuwave\Lighthouse\Support\Contracts\Directive|null
     */
    protected function resolve(string $directiveName): ?Directive
    {
        if ($className = Arr::get($this->resolvedClassnames, $directiveName)) {
            return app($className);
        }

        return null;
    }

    /**
     * @param  string  $directiveName
     * @return \Nuwave\Lighthouse\Support\Contracts\Directive
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function createOrFail(string $directiveName): Directive
    {
        foreach ($this->directiveBaseNamespaces as $baseNamespace) {
            $className = $baseNamespace.'\\'.Str::studly($directiveName).'Directive';
            if (class_exists($className)) {
                $directive = app($className);

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
     * @param  string  $directiveName
     * @param  string  $className
     * @return $this
     */
    public function addResolved(string $directiveName, string $className): self
    {
        // Bail to respect the priority of namespaces, the first
        // resolved directive is kept
        if (in_array($directiveName, $this->resolvedClassnames, true)) {
            return $this;
        }

        $this->resolvedClassnames[$directiveName] = $className;

        return $this;
    }

    /**
     * @param  string  $directiveName
     * @param  string  $className
     * @return $this
     */
    public function setResolved(string $directiveName, string $className): self
    {
        $this->resolvedClassnames[$directiveName] = $className;

        return $this;
    }

    /**
     * @return $this
     */
    public function clearResolved(): self
    {
        $this->resolvedClassnames = [];

        return $this;
    }

    /**
     * Set the given definition on the directive.
     *
     * @param  \Nuwave\Lighthouse\Support\Contracts\Directive  $directive
     * @param  \GraphQL\Language\AST\Node  $node
     * @return \Nuwave\Lighthouse\Support\Contracts\Directive
     */
    protected function hydrate(Directive $directive, Node $node): Directive
    {
        return $directive instanceof BaseDirective
            ? $directive->hydrate($node)
            : $directive;
    }

    /**
     * Get all directives of a certain type that are associated with an AST node.
     *
     * @param  \GraphQL\Language\AST\Node  $node
     * @param  string  $directiveClass
     * @return \Illuminate\Support\Collection <$directiveClass>
     */
    public function createAssociatedDirectivesOfType(Node $node, string $directiveClass): Collection
    {
        return (new Collection($node->directives))
            ->map(function (DirectiveNode $directive) use ($node): Directive {
                return $this->create($directive->name->value, $node);
            })
            ->filter(function (Directive $directive) use ($directiveClass): bool {
                return $directive instanceof $directiveClass;
            });
    }

    /**
     * Get a single directive of a type that belongs to an AST node.
     *
     * Use this for directives types that can only occur once, such as field resolvers.
     * This throws if more than one such directive is found.
     *
     * @param  \GraphQL\Language\AST\Node  $node
     * @param  string  $directiveClass
     * @return \Nuwave\Lighthouse\Support\Contracts\Directive|null
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    public function createSingleDirectiveOfType(Node $node, string $directiveClass): ?Directive
    {
        $directives = $this->createAssociatedDirectivesOfType($node, $directiveClass);

        if ($directives->count() > 1) {
            $directiveNames = $directives->implode(', ');

            throw new DirectiveException(
                "Node [{$node->name->value}] can only have one directive of type [{$directiveClass}] but found [{$directiveNames}]"
            );
        }

        return $directives->first();
    }
}

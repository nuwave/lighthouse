<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use GraphQL\Language\AST\Node;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\DirectiveNode;
use Illuminate\Contracts\Events\Dispatcher;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;

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
    protected $resolved = [];

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
        $directives = new Collection([
            // User defined directives (top priority)
            config('lighthouse.namespaces.directives'),

            // Plugin developers defined directives
            $dispatcher->dispatch(new RegisterDirectiveNamespaces),
        ]);

        /*
         * Allow a smooth transition away from the deprecated between directives.
         * @deprecated
         */
        if (config('new_between_directives')) {
            $directives->push('Nuwave\\Lighthouse\\Between');
        }

        // Lighthouse defined directives
        $directives->push('Nuwave\\Lighthouse\\Schema\\Directives');

        $this->directiveBaseNamespaces = $directives
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
        $directive = $this->resolve($directiveName) ?? $this->createOrFail($directiveName);

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
        if ($className = Arr::get($this->resolved, $directiveName)) {
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
        if (in_array($directiveName, $this->resolved, true)) {
            return $this;
        }

        $this->resolved[$directiveName] = $className;

        return $this;
    }

    /**
     * @param  string  $directiveName
     * @param  string  $className
     * @return $this
     */
    public function setResolved(string $directiveName, string $className): self
    {
        $this->resolved[$directiveName] = $className;

        return $this;
    }

    /**
     * @return $this
     */
    public function clearResolved(): self
    {
        $this->resolved = [];

        return $this;
    }

    /**
     * Set the given definition on the directive.
     *
     * @param  \Nuwave\Lighthouse\Support\Contracts\Directive  $directive
     * @param  \GraphQL\Language\AST\TypeSystemDefinitionNode  $definitionNode
     * @return \Nuwave\Lighthouse\Support\Contracts\Directive
     */
    protected function hydrate(Directive $directive, $definitionNode): Directive
    {
        return $directive instanceof BaseDirective
            ? $directive->hydrate($definitionNode)
            : $directive;
    }

    /**
     * Get all directives of a certain type that are associated with an AST node.
     *
     * @param  \GraphQL\Language\AST\Node  $node
     * @param  string  $directiveClass
     * @return \Illuminate\Support\Collection <$directiveClass>
     */
    protected function createAssociatedDirectivesOfType(Node $node, string $directiveClass): Collection
    {
        return (new Collection($node->directives))
            ->map(function (DirectiveNode $directive) use ($node) {
                return $this->create($directive->name->value, $node);
            })
            ->filter(function (Directive $directive) use ($directiveClass) {
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
    protected function createSingleDirectiveOfType(Node $node, string $directiveClass): ?Directive
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

    /**
     * @param  \GraphQL\Language\AST\Node  $node
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\NodeManipulator>
     */
    public function createNodeManipulators(Node $node): Collection
    {
        return $this->createAssociatedDirectivesOfType($node, NodeManipulator::class);
    }

    /**
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\FieldManipulator>
     */
    public function createFieldManipulators(FieldDefinitionNode $fieldDefinition): Collection
    {
        return $this->createAssociatedDirectivesOfType($fieldDefinition, FieldManipulator::class);
    }

    /**
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $inputValueDefinition
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\ArgManipulator>
     */
    public function createArgManipulators(InputValueDefinitionNode $inputValueDefinition): Collection
    {
        return $this->createAssociatedDirectivesOfType($inputValueDefinition, ArgManipulator::class);
    }

    /**
     * Get the node resolver directive for the given type definition.
     *
     * @param  \GraphQL\Language\AST\TypeDefinitionNode  $node
     * @return \Nuwave\Lighthouse\Support\Contracts\NodeResolver|null
     */
    public function createNodeResolver(TypeDefinitionNode $node): ?NodeResolver
    {
        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->createSingleDirectiveOfType($node, NodeResolver::class);
    }

    /**
     * Check if the given node has a type resolver directive handler assigned to it.
     *
     * @param  \GraphQL\Language\AST\TypeDefinitionNode  $typeDefinition
     * @return bool
     */
    public function hasNodeResolver(TypeDefinitionNode $typeDefinition): bool
    {
        return $this->createNodeResolver($typeDefinition) instanceof NodeResolver;
    }

    /**
     * Check if the given field has a field resolver directive handler assigned to it.
     *
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @return bool
     */
    public function hasFieldResolver(FieldDefinitionNode $fieldDefinition): bool
    {
        return $this->createFieldResolver($fieldDefinition) instanceof FieldResolver;
    }

    /**
     * Check if field has one or more FieldMiddleware directives associated with it.
     *
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $field
     * @return bool
     */
    public function hasFieldMiddleware(FieldDefinitionNode $field): bool
    {
        return $this->createFieldMiddleware($field)->count() > 1;
    }

    /**
     * Get handler for field.
     *
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $field
     * @return \Nuwave\Lighthouse\Support\Contracts\FieldResolver|null
     */
    public function createFieldResolver(FieldDefinitionNode $field): ?FieldResolver
    {
        return $this->createSingleDirectiveOfType($field, FieldResolver::class);
    }

    /**
     * Get all middleware directive for a type definitions.
     *
     * @param  \GraphQL\Language\AST\Node  $typeDefinition
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\NodeMiddleware>
     */
    public function createNodeMiddleware(Node $typeDefinition): Collection
    {
        return $this->createAssociatedDirectivesOfType($typeDefinition, NodeMiddleware::class);
    }

    /**
     * Get middleware for field.
     *
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\FieldMiddleware>
     */
    public function createFieldMiddleware($fieldDefinition): Collection
    {
        return $this->createAssociatedDirectivesOfType($fieldDefinition, FieldMiddleware::class);
    }

    /**
     * Create `ArgTransformerDirective` instances from `InputValueDefinitionNode`.
     *
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $arg
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective>
     */
    public function createArgTransformers(InputValueDefinitionNode $arg): Collection
    {
        return $this->createAssociatedDirectivesOfType($arg, ArgTransformerDirective::class);
    }

    /**
     * Create `ArgDirective` instances from `InputValueDefinitionNode`.
     *
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $arg
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\ArgDirective>
     */
    public function createArgDirectives(InputValueDefinitionNode $arg): Collection
    {
        return $this->createAssociatedDirectivesOfType($arg, ArgDirective::class);
    }

    /**
     * Get middleware for array arguments.
     *
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $arg
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray>
     */
    public function createArgDirectivesForArray(InputValueDefinitionNode $arg): Collection
    {
        return $this->createAssociatedDirectivesOfType($arg, ArgDirectiveForArray::class);
    }

    /**
     * Get query builders for arguments.
     *
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $arg
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective>
     */
    public function createArgBuilderDirective(InputValueDefinitionNode $arg): Collection
    {
        return $this->createAssociatedDirectivesOfType($arg, ArgBuilderDirective::class);
    }
}

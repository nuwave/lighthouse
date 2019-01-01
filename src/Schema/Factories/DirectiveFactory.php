<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Illuminate\Support\Arr;
use GraphQL\Language\AST\Node;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
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
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Events\RegisteringDirectiveBaseNamespaces;
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
     */
    public function __construct()
    {
        $this->directiveBaseNamespaces = collect([
            // User defined directives (top priority)
            config('lighthouse.namespaces.directives'),

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
     * @param string                        $directiveName
     * @param TypeSystemDefinitionNode|null $definitionNode
     *
     * @throws DirectiveException
     *
     * @return Directive
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
     * @param string $directiveName
     *
     * @return Directive|null
     */
    protected function resolve(string $directiveName): ?Directive
    {
        if ($className = Arr::get($this->resolved, $directiveName)) {
            return app($className);
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
     * @param string $directiveName
     * @param string $className
     *
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
     * @param string $directiveName
     * @param string $className
     *
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
     * Get all directives of a certain type that are associated with an AST node.
     *
     * @param Node   $node
     * @param string $directiveClass
     *
     * @return Collection<$directiveClass>
     */
    protected function createAssociatedDirectivesOfType(Node $node, string $directiveClass): Collection
    {
        return collect($node->directives)
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
     * @param Node   $node
     * @param string $directiveClass
     *
     * @throws DirectiveException
     *
     * @return Directive|null
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
     * @param Node $node
     *
     * @return Collection<NodeManipulator>
     */
    public function createNodeManipulators(Node $node): Collection
    {
        return $this->createAssociatedDirectivesOfType($node, NodeManipulator::class);
    }

    /**
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return Collection<FieldManipulator>
     */
    public function createFieldManipulators(FieldDefinitionNode $fieldDefinition): Collection
    {
        return $this->createAssociatedDirectivesOfType($fieldDefinition, FieldManipulator::class);
    }

    /**
     * @param $inputValueDefinition
     *
     * @return Collection<ArgManipulator>
     */
    public function createArgManipulators(InputValueDefinitionNode $inputValueDefinition): Collection
    {
        return $this->createAssociatedDirectivesOfType($inputValueDefinition, ArgManipulator::class);
    }

    /**
     * Get the node resolver directive for the given type definition.
     *
     * @param TypeDefinitionNode $node
     *
     * @throws DirectiveException
     *
     * @return NodeResolver|null
     */
    public function createNodeResolver(TypeDefinitionNode $node): ?NodeResolver
    {
        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->createSingleDirectiveOfType($node, NodeResolver::class);
    }

    /**
     * Check if the given node has a type resolver directive handler assigned to it.
     *
     * @param TypeDefinitionNode $typeDefinition
     *
     * @throws DirectiveException
     *
     * @return bool
     */
    public function hasNodeResolver(TypeDefinitionNode $typeDefinition): bool
    {
        return $this->createNodeResolver($typeDefinition) instanceof NodeResolver;
    }

    /**
     * Check if the given field has a field resolver directive handler assigned to it.
     *
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @throws DirectiveException
     *
     * @return bool
     */
    public function hasFieldResolver(FieldDefinitionNode $fieldDefinition): bool
    {
        return $this->createFieldResolver($fieldDefinition) instanceof FieldResolver;
    }

    /**
     * Check if field has one or more FieldMiddleware directives associated with it.
     *
     * @param FieldDefinitionNode $field
     *
     * @return bool
     */
    public function hasFieldMiddleware(FieldDefinitionNode $field): bool
    {
        return $this->createFieldMiddleware($field)->count() > 1;
    }

    /**
     * Get handler for field.
     *
     * @param FieldDefinitionNode $field
     *
     * @throws DirectiveException
     *
     * @return FieldResolver|null
     */
    public function createFieldResolver(FieldDefinitionNode $field): ?FieldResolver
    {
        return $this->createSingleDirectiveOfType($field, FieldResolver::class);
    }

    /**
     * Get all middleware directive for a type definitions.
     *
     * @param Node $typeDefinition
     *
     * @return Collection<NodeMiddleware>
     */
    public function createNodeMiddleware(Node $typeDefinition): Collection
    {
        return $this->createAssociatedDirectivesOfType($typeDefinition, NodeMiddleware::class);
    }

    /**
     * Get middleware for field.
     *
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return Collection<FieldMiddleware>
     */
    public function createFieldMiddleware($fieldDefinition): Collection
    {
        return $this->createAssociatedDirectivesOfType($fieldDefinition, FieldMiddleware::class);
    }

    /**
     * Create `ArgTransformerDirective` instances from `InputValueDefinitionNode`.
     *
     * @param InputValueDefinitionNode $arg
     *
     * @return Collection<ArgTransformerDirective>
     */
    public function createArgTransformers(InputValueDefinitionNode $arg): Collection
    {
        return $this->createAssociatedDirectivesOfType($arg, ArgTransformerDirective::class);
    }

    /**
     * Create `ArgDirective` instances from `InputValueDefinitionNode`.
     *
     * @param InputValueDefinitionNode $arg
     *
     * @return Collection<ArgDirective>
     */
    public function createArgDirectives(InputValueDefinitionNode $arg): Collection
    {
        return $this->createAssociatedDirectivesOfType($arg, ArgDirective::class);
    }

    /**
     * Get middleware for array arguments.
     *
     * @param InputValueDefinitionNode $arg
     *
     * @return Collection<ArgDirectiveForArray>
     */
    public function createArgDirectivesForArray(InputValueDefinitionNode $arg): Collection
    {
        return $this->createAssociatedDirectivesOfType($arg, ArgDirectiveForArray::class);
    }

    /**
     * Get filters for arguments.
     *
     * @param InputValueDefinitionNode $arg
     *
     * @return Collection<ArgFilterDirective>
     */
    public function createArgFilterDirective(InputValueDefinitionNode $arg): Collection
    {
        return $this->createAssociatedDirectivesOfType($arg, ArgFilterDirective::class);
    }
}

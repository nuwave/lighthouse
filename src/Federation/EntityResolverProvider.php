<?php

namespace Nuwave\Lighthouse\Federation;

use Closure;
use GraphQL\Error\Error;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Exceptions\FederationException;
use Nuwave\Lighthouse\Federation\Directives\KeyDirective;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\ModelDirective;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Support\Utils;

/**
 * @phpstan-type SingleEntityResolverFn \Closure(array<string, mixed>): mixed
 * @phpstan-type EntityResolver SingleEntityResolverFn|BatchedEntityResolver
 */
class EntityResolverProvider
{
    /**
     * @var \GraphQL\Type\Schema
     */
    protected $schema;

    /**
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveLocator;

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $configRepository;

    /**
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /**
     * Maps from __typename to definitions.
     *
     * @var array<string, \GraphQL\Language\AST\ObjectTypeDefinitionNode>
     */
    protected $definitions;

    /**
     * Maps from __typename to resolver.
     *
     * @var array<string, SingleEntityResolverFn|BatchedEntityResolver>
     */
    protected $resolvers;

    public function __construct(
        SchemaBuilder $schemaBuilder,
        DirectiveLocator $directiveLocator,
        ConfigRepository $configRepository,
        Container $container
    ) {
        $this->schema = $schemaBuilder->schema();
        $this->directiveLocator = $directiveLocator;
        $this->configRepository = $configRepository;
        $this->container = $container;
    }

    public static function missingResolver(string $typename): string
    {
        return "Could not locate a resolver for __typename `{$typename}`.";
    }

    public static function unknownTypename(string $typename): string
    {
        return "Unknown __typename `{$typename}`.";
    }

    /**
     * @return EntityResolver
     */
    public function resolver(string $typename): callable
    {
        if (isset($this->resolvers[$typename])) {
            return $this->resolvers[$typename];
        }

        $resolver = $this->resolverFromClass($typename)
            ?? $this->resolverFromModel($typename)
            ?? null;

        if (null === $resolver) {
            throw new Error(self::missingResolver($typename));
        }

        $this->resolvers[$typename] = $resolver;

        return $resolver;
    }

    public function typeDefinition(string $typename): ObjectTypeDefinitionNode
    {
        if (isset($this->definitions[$typename])) {
            return $this->definitions[$typename];
        }

        $type = null;
        try {
            $type = $this->schema->getType($typename);
        } catch (DefinitionException $definitionException) {
            // Signalizes the type is unknown, handled by the null check below
        }
        if (null === $type) {
            throw new Error(self::unknownTypename($typename));
        }

        /**
         * TODO remove when upgrading graphql-php.
         *
         * @var (\GraphQL\Language\AST\Node&\GraphQL\Language\AST\TypeDefinitionNode)|null $definition
         */
        $definition = $type->astNode;
        if (null === $definition) {
            throw new FederationException("Must provide AST definition for type `{$typename}`.");
        }

        if (! $definition instanceof ObjectTypeDefinitionNode) {
            throw new Error("Expected __typename `{$typename}` to be ObjectTypeDefinition, got {$definition->kind}.");
        }

        $this->definitions[$typename] = $definition;

        return $definition;
    }

    /**
     * @return EntityResolver|null
     */
    protected function resolverFromClass(string $typename): ?callable
    {
        $resolverClass = Utils::namespaceClassname(
            $typename,
            (array) config('lighthouse.federation.entities_resolver_namespace'),
            'class_exists'
        );

        if (null === $resolverClass) {
            return null;
        }

        if (is_a($resolverClass, BatchedEntityResolver::class, true)) {
            return $this->container->make($resolverClass);
        }

        return Utils::constructResolver($resolverClass, '__invoke');
    }

    /**
     * @return SingleEntityResolverFn|null
     */
    protected function resolverFromModel(string $typeName): ?Closure
    {
        $definition = $this->typeDefinition($typeName);

        $model = ModelDirective::modelClass($definition) ?? $typeName;

        /** @var class-string<\Illuminate\Database\Eloquent\Model>|null $modelClass */
        $modelClass = Utils::namespaceClassname(
            $model,
            (array) $this->configRepository->get('lighthouse.namespaces.models'),
            static function (string $classCandidate): bool {
                return is_subclass_of($classCandidate, Model::class);
            }
        );
        if (null === $modelClass) {
            return null;
        }

        $keyFieldsSelections = $this->keyFieldsSelections($definition);

        return function (array $representation) use ($keyFieldsSelections, $modelClass): ?Model {
            /** @var \Illuminate\Database\Eloquent\Builder $builder */
            $builder = $modelClass::query();
            $this->constrainKeys($builder, $keyFieldsSelections, $representation);

            $results = $builder->get();
            if ($results->count() > 1) {
                throw new Error('The query returned more than one result.');
            }

            return $results->first();
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<\GraphQL\Language\AST\SelectionSetNode>  $keyFieldsSelections
     * @param  array<string, mixed>  $representation
     */
    protected function constrainKeys(Builder $builder, Collection $keyFieldsSelections, array $representation): void
    {
        $this->applySatisfiedSelection(
            $builder,
            $this->firstSatisfiedKeyFields($keyFieldsSelections, $representation),
            $representation
        );
    }

    /**
     * @param  array<string, mixed>  $representation
     */
    protected function satisfiesKeyFields(SelectionSetNode $keyFields, array $representation): bool
    {
        /**
         * Fragments or spreads are not allowed in key fields.
         *
         * @see \Nuwave\Lighthouse\Federation\SchemaValidator
         *
         * @var \GraphQL\Language\AST\FieldNode $field
         */
        foreach ($keyFields->selections as $field) {
            $fieldName = $field->name->value;
            $value = $representation[$fieldName] ?? null;
            if (null === $value) {
                return false;
            }

            $subSelection = $field->selectionSet;
            if (null !== $subSelection) {
                if (! is_array($value)) {
                    return false;
                }

                $subSelectionProvidesKeys = $this->satisfiesKeyFields($subSelection, $value);
                if (! $subSelectionProvidesKeys) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $representation
     */
    protected function applySatisfiedSelection(Builder $builder, SelectionSetNode $keyFields, array $representation): void
    {
        /**
         * Fragments or spreads are not allowed in key fields.
         *
         * @var \GraphQL\Language\AST\FieldNode $field
         */
        foreach ($keyFields->selections as $field) {
            $fieldName = $field->name->value;
            $value = $representation[$fieldName];

            $subSelection = $field->selectionSet;
            if (null === $subSelection) {
                $builder->where($fieldName, $value);

                return;
            }

            $this->applySatisfiedSelection($builder, $subSelection, $representation);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<\GraphQL\Language\AST\SelectionSetNode>
     */
    public function keyFieldsSelections(ObjectTypeDefinitionNode $definition): Collection
    {
        return $this->directiveLocator
            ->associatedOfType($definition, KeyDirective::class)
            ->map(static function (KeyDirective $keyDirective): SelectionSetNode {
                return $keyDirective->fields();
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<\GraphQL\Language\AST\SelectionSetNode>  $keyFieldsSelections
     * @param  array<string, mixed>  $representation
     */
    public function firstSatisfiedKeyFields(Collection $keyFieldsSelections, array $representation): SelectionSetNode
    {
        $satisfiedKeyFields = $keyFieldsSelections->first(
            function (SelectionSetNode $keyFields) use ($representation): bool {
                return $this->satisfiesKeyFields($keyFields, $representation);
            }
        );

        if (null === $satisfiedKeyFields) {
            throw new Error('Representation does not satisfy any set of uniquely identifying keys: ' . \Safe\json_encode($representation));
        }

        return $satisfiedKeyFields;
    }
}

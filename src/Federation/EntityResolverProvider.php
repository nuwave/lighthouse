<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Type\Schema;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Federation\Directives\KeyDirective;
use Nuwave\Lighthouse\GlobalId\GlobalId;
use Nuwave\Lighthouse\GlobalId\GlobalIdDirective;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\ModelDirective;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Support\Utils;

/**
 * @phpstan-type SingleEntityResolverFn callable(array<string, mixed>): mixed
 * @phpstan-type EntityResolver SingleEntityResolverFn|BatchedEntityResolver
 */
class EntityResolverProvider
{
    protected Schema $schema;

    /**
     * Maps from __typename to definitions.
     *
     * @var array<string, \GraphQL\Language\AST\ObjectTypeDefinitionNode>
     */
    protected array $definitions;

    /**
     * Maps from __typename to resolver.
     *
     * @var array<string, SingleEntityResolverFn|BatchedEntityResolver>
     */
    protected array $resolvers;

    public function __construct(
        SchemaBuilder $schemaBuilder,
        protected DirectiveLocator $directiveLocator,
        protected ConfigRepository $configRepository,
        protected Container $container,
        protected GlobalId $globalId,
    ) {
        $this->schema = $schemaBuilder->schema();
    }

    public static function missingResolver(string $typename): string
    {
        return "Could not locate a resolver for __typename `{$typename}`.";
    }

    public static function unknownTypename(string $typename): string
    {
        return "Unknown __typename `{$typename}`.";
    }

    /** @return EntityResolver */
    public function resolver(string $typename): callable
    {
        if (isset($this->resolvers[$typename])) {
            return $this->resolvers[$typename];
        }

        $resolver = $this->resolverFromClass($typename)
            ?? $this->resolverFromModel($typename)
            ?? throw new Error(self::missingResolver($typename));

        $this->resolvers[$typename] = $resolver;

        return $resolver;
    }

    public function typeDefinition(string $typename): ObjectTypeDefinitionNode
    {
        if (isset($this->definitions[$typename])) {
            return $this->definitions[$typename];
        }

        $type = $this->schema->getType($typename)
            ?? throw new Error(self::unknownTypename($typename));

        $definition = $type->astNode()
            ?? throw new FederationException("Must provide AST definition for type `{$typename}`.");

        if (! $definition instanceof ObjectTypeDefinitionNode) {
            throw new Error("Expected __typename `{$typename}` to be ObjectTypeDefinition, got {$definition->kind}.");
        }

        $this->definitions[$typename] = $definition;

        return $definition;
    }

    /** @return EntityResolver|null */
    protected function resolverFromClass(string $typename): ?callable
    {
        $resolverClass = Utils::namespaceClassname(
            $typename,
            (array) config('lighthouse.federation.entities_resolver_namespace'),
            'class_exists',
        );
        if ($resolverClass === null) {
            return null;
        }

        if (is_a($resolverClass, BatchedEntityResolver::class, true)) {
            return $this->container->make($resolverClass);
        }

        return Utils::constructResolver($resolverClass, '__invoke');
    }

    /** @return SingleEntityResolverFn|null */
    protected function resolverFromModel(string $typeName): ?callable
    {
        $definition = $this->typeDefinition($typeName);

        $model = ModelDirective::modelClass($definition) ?? $typeName;

        /** @var class-string<\Illuminate\Database\Eloquent\Model>|null $modelClass */
        $modelClass = Utils::namespaceClassname(
            $model,
            (array) $this->configRepository->get('lighthouse.namespaces.models'),
            static fn (string $classCandidate): bool => is_subclass_of($classCandidate, Model::class),
        );
        if ($modelClass === null) {
            return null;
        }

        $keyFieldsSelections = $this->keyFieldsSelections($definition);

        return function (array $representation) use ($keyFieldsSelections, $modelClass, $definition): ?Model {
            $builder = $modelClass::query();

            $this->constrainKeys($builder, $keyFieldsSelections, $representation, $definition);

            $results = $builder->get();
            if ($results->count() > 1) {
                throw new Error('The query returned more than one result.');
            }

            $model = $results->first();
            if ($model !== null) {
                $this->hydrateExternalFields($model, $representation, $definition);
            }

            return $model;
        };
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  \Illuminate\Support\Collection<int, \GraphQL\Language\AST\SelectionSetNode>  $keyFieldsSelections
     * @param  array<string, mixed>  $representation
     */
    protected function constrainKeys(EloquentBuilder $builder, Collection $keyFieldsSelections, array $representation, ObjectTypeDefinitionNode $definition): void
    {
        $this->applySatisfiedSelection(
            $builder,
            $this->firstSatisfiedKeyFields($keyFieldsSelections, $representation),
            $representation,
            $definition,
        );
    }

    /** @param  array<string, mixed>  $representation */
    protected function satisfiesKeyFields(SelectionSetNode $keyFields, array $representation): bool
    {
        foreach ($keyFields->selections as $field) {
            /** @see SchemaValidator */
            assert($field instanceof FieldNode, 'Fragments or spreads are not allowed in key fields');

            $fieldName = $field->name->value;
            $value = $representation[$fieldName] ?? null;
            if ($value === null) {
                return false;
            }

            $subSelection = $field->selectionSet;
            if ($subSelection !== null) {
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
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  array<string, mixed>  $representation
     */
    protected function applySatisfiedSelection(EloquentBuilder $builder, SelectionSetNode $keyFields, array $representation, ObjectTypeDefinitionNode $definition): void
    {
        foreach ($keyFields->selections as $field) {
            /** @see SchemaValidator */
            assert($field instanceof FieldNode, 'Fragments or spreads are not allowed in key fields');

            $fieldName = $field->name->value;
            $value = $representation[$fieldName];

            $subSelection = $field->selectionSet;
            if ($subSelection === null) {
                $builder->where(
                    $fieldName,
                    $this->hasFieldWithDirective($definition, $fieldName, GlobalIdDirective::NAME)
                        ? $this->globalId->decodeID($value)
                        : $value,
                );

                return;
            }

            $builder->whereHas(
                $fieldName,
                fn (EloquentBuilder $nestedBuilder) => $this->applySatisfiedSelection($nestedBuilder, $subSelection, $value, $definition),
            );
        }
    }

    private function hasFieldWithDirective(ObjectTypeDefinitionNode $definition, string $fieldName, string $directiveName): bool
    {
        $field = ASTHelper::firstByName($definition->fields, $fieldName);
        if ($field === null) {
            return false;
        }

        return ASTHelper::hasDirective($field, $directiveName);
    }

    /** @return \Illuminate\Support\Collection<int, \GraphQL\Language\AST\SelectionSetNode> */
    public function keyFieldsSelections(ObjectTypeDefinitionNode $definition): Collection
    {
        return $this->directiveLocator
            ->associatedOfType($definition, KeyDirective::class)
            ->map(static fn (KeyDirective $keyDirective): SelectionSetNode => $keyDirective->fields());
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \GraphQL\Language\AST\SelectionSetNode>  $keyFieldsSelections
     * @param  array<string, mixed>  $representation
     */
    public function firstSatisfiedKeyFields(Collection $keyFieldsSelections, array $representation): SelectionSetNode
    {
        return $keyFieldsSelections->first(fn (SelectionSetNode $keyFields): bool => $this->satisfiesKeyFields($keyFields, $representation))
            ?? throw new Error('Representation does not satisfy any set of uniquely identifying keys: ' . \Safe\json_encode($representation));
    }

    /** @param  array<string, mixed>  $representation */
    protected function hydrateExternalFields(Model $model, array $representation, ObjectTypeDefinitionNode $definition): void
    {
        foreach ($definition->fields as $field) {
            if (ASTHelper::hasDirective($field, 'external')) {
                $fieldName = $field->name->value;
                if (array_key_exists($fieldName, $representation)) {
                    $value = $representation[$fieldName];
                    if (ASTHelper::hasDirective($field, GlobalIdDirective::NAME)) {
                        $value = $this->globalId->decodeID($value);
                    }

                    $model->setAttribute($fieldName, $value);
                }
            }
        }
    }
}

<?php

namespace Nuwave\Lighthouse\Federation;

use Closure;
use GraphQL\Error\Error;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
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
     * Maps from __typename to definitions.
     *
     * @var array<string, \GraphQL\Language\AST\ObjectTypeDefinitionNode>
     */
    protected $definitions;

    /**
     * Maps from __typename to resolver.
     *
     * @var array<string, \Closure(array<string, mixed>): mixed>
     */
    protected $resolvers;

    public function __construct(SchemaBuilder $schemaBuilder, DirectiveLocator $directiveLocator, ConfigRepository $configRepository)
    {
        $this->schema = $schemaBuilder->schema();
        $this->directiveLocator = $directiveLocator;
        $this->configRepository = $configRepository;
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
     * @return \Closure(array<string, mixed> $representations): mixed
     */
    public function resolver(string $typename): Closure
    {
        if (isset($this->resolvers[$typename])) {
            return $this->resolvers[$typename];
        }

        $resolver = $this->resolverFromClass($typename)
            ?? $this->resolverFromModel($typename)
            ?? null;

        if ($resolver === null) {
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
        if ($type === null) {
            throw new Error(self::unknownTypename($typename));
        }

        /**
         * TODO remove when upgrading graphql-php.
         *
         * @var (\GraphQL\Language\AST\Node&\GraphQL\Language\AST\TypeDefinitionNode)|null $definition
         */
        $definition = $type->astNode;
        if ($definition === null) {
            throw new FederationException("Must provide AST definition for type `{$typename}`.");
        }

        if (! $definition instanceof ObjectTypeDefinitionNode) {
            throw new Error("Expected __typename `{$typename}` to be ObjectTypeDefinition, got {$definition->kind}.");
        }

        $this->definitions[$typename] = $definition;

        return $definition;
    }

    protected function resolverFromClass(string $typename): ?Closure
    {
        $resolverClass = Utils::namespaceClassname(
            $typename,
            (array) config('lighthouse.federation.entities_resolver_namespace'),
            'class_exists'
        );

        if ($resolverClass === null) {
            return null;
        }

        return Utils::constructResolver($resolverClass, '__invoke');
    }

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
        if ($modelClass === null) {
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
     * @param array<string, mixed> $representation
     */
    protected function satisfiesKeyFields(SelectionSetNode $keyFields, array $representation): bool
    {
        /**
         * Fragments or spreads are not allowed in key fields.
         * @see \Nuwave\Lighthouse\Federation\SchemaValidator
         *
         * @var \GraphQL\Language\AST\FieldNode $field
         */
        foreach ($keyFields->selections as $field) {
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
     * @param array<string, mixed> $representation
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
            if ($subSelection === null) {
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
     * @param \Illuminate\Support\Collection<\GraphQL\Language\AST\SelectionSetNode> $keyFieldsSelections
     * @param array<string, mixed> $representation
     */
    public function firstSatisfiedKeyFields(Collection $keyFieldsSelections, array $representation): SelectionSetNode
    {
        $satisfiedKeyFields = $keyFieldsSelections->first(
            function (SelectionSetNode $keyFields) use ($representation): bool {
                return $this->satisfiesKeyFields($keyFields, $representation);
            }
        );

        if ($satisfiedKeyFields === null) {
            throw new Error('Representation does not satisfy any set of uniquely identifying keys: '.\Safe\json_encode($representation));
        }

        return $satisfiedKeyFields;
    }
}

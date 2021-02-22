<?php

namespace Nuwave\Lighthouse\Federation;

use Closure;
use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\SelectionSetNode;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\FederationException;
use Nuwave\Lighthouse\Federation\Directives\KeyDirective;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\ModelDirective;
use Nuwave\Lighthouse\Support\Utils;

class EntityResolverProvider
{
    /**
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected $documentAST;

    /**
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveLocator;

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $configRepository;

    /**
     * Maps from __typename to resolver.
     *
     * @var array<string, \Closure(array<string, mixed> $representations): mixed>
     */
    protected $resolvers;

    public function __construct(ASTBuilder $astBuilder, DirectiveLocator $directiveLocator, ConfigRepository $configRepository)
    {
        $this->documentAST = $astBuilder->documentAST();
        $this->directiveLocator = $directiveLocator;
        $this->configRepository = $configRepository;
    }

    /**
     * @return \Closure(array<string, mixed> $representations): mixed
     */
    public function resolver(string $typename): Closure
    {
        $resolver = $this->resolvers[$typename] ?? null;

        if ($resolver === null) {
            $resolver = $this->resolverFromClass($typename)
                ?? $this->resolverFromModel($typename);

            if ($resolver === null) {
                throw new FederationException("Could not locate resolver for __typename: {$typename}");
            }
            $this->resolvers[$typename] = $resolver;

            return $resolver;
        }

        return $resolver;
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
        /**
         * We validated this in _Any.
         *
         * @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $type
         */
        $type = $this->documentAST->types[$typeName];

        $model = ModelDirective::modelClass($type) ?? $typeName;

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

        $keyFieldsSelections = $this->directiveLocator
            ->associatedOfType($type, KeyDirective::class)
            ->map(static function (KeyDirective $keyDirective): SelectionSetNode {
                return $keyDirective->fields();
            });

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
     * @param  \GraphQL\Language\AST\NodeList<\GraphQL\Language\AST\SelectionSetNode>  $keyFieldsSelections
     * @param  array<string, mixed>  $representation
     */
    protected function constrainKeys(Builder $builder, NodeList $keyFieldsSelections, array $representation): void
    {
        $satisfiedKeyFields = null;
        foreach ($keyFieldsSelections as $keyFields) {
            if ($this->satisfiesKeyFields($keyFields, $representation)) {
                $satisfiedKeyFields = $keyFields;
                break;
            }
        }

        if ($satisfiedKeyFields === null) {
            throw new FederationException('Representation does not satisfy any set of uniquely identifying keys: ' . \Safe\json_encode($representation));
        }

        $this->applySatisfiedSelection($builder, $keyFields, $representation);
    }

    /**
     * @param array<string, mixed> $representation
     */
    protected function satisfiesKeyFields(SelectionSetNode $keyFields, array $representation): bool
    {
        /**
         * Fragments or spreads are not allowed in key fields.
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
}

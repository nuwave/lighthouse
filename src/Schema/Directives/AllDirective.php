<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Select\SelectHelper;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AllDirective extends BaseDirective implements FieldResolver, FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Fetch all Eloquent models and return the collection as the result.
"""
directive @all(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  Mutually exclusive with `builder`.
  """
  model: String

  """
  Point to a function that provides a Query Builder instance.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  Mutually exclusive with `model`.
  """
  builder: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Collection {
            if ($this->directiveHasArgument('builder')) {
                $builderResolver = $this->getResolverFromArgument('builder');

                $query = $builderResolver($root, $args, $context, $resolveInfo);
                assert(
                    $query instanceof QueryBuilder || $query instanceof EloquentBuilder || $query instanceof ScoutBuilder || $query instanceof Relation,
                    "The method referenced by the builder argument of the @{$this->name()} directive on {$this->nodeName()} must return a Builder or Relation."
                );
            } else {
                $query = $this->getModelClass()::query();
            }

            $builder = $resolveInfo
                ->enhanceBuilder(
                    $query,
                    $this->directiveArgValue('scopes', [])
                );

            if (config('lighthouse.optimized_selects')) {
                if ($builder instanceof EloquentBuilder) {
                    $fieldSelection = array_keys($resolveInfo->getFieldSelection(1));

                    $model = $builder->getModel();

                    $selectColumns = SelectHelper::getSelectColumns(
                        $this->definitionNode,
                        $fieldSelection,
                        get_class($model)
                    );

                    if (empty($selectColumns)) {
                        throw new Error('The select column is empty.');
                    }

                    $query = $builder->getQuery();

                    if (null !== $query->columns) {
                        $bindings = $query->getRawBindings();

                        $expressions = array_filter($query->columns, function ($column) {
                            return $column instanceof Expression;
                        });

                        $builder = $builder->select(array_unique(array_merge($selectColumns, $expressions)));

                        $builder = $builder->addBinding($bindings['select'], 'select');
                    } else {
                        $builder = $builder->select($selectColumns);
                    }

                    /** @var string|string[] $keyName */
                    $keyName = $model->getKeyName();
                    if (is_string($keyName)) {
                        $keyName = [$keyName];
                    }

                    foreach ($keyName as $name) {
                        $query->orderBy($name);
                    }
                }
            }

            return $builder->get();
        });

        return $fieldValue;
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode &$parentType)
    {
        $this->validateMutuallyExclusiveArguments(['model', 'builder']);
    }
}

<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
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

    public function resolveField(FieldValue $fieldValue): callable
    {
        return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Collection {
            if ($this->directiveHasArgument('builder')) {
                $builderResolver = $this->getResolverFromArgument('builder');

                $query = $builderResolver($root, $args, $context, $resolveInfo);
                assert(
                    $query instanceof QueryBuilder || $query instanceof EloquentBuilder || $query instanceof ScoutBuilder || $query instanceof Relation,
                    "The method referenced by the builder argument of the @{$this->name()} directive on {$this->nodeName()} must return a Builder or Relation.",
                );
            } else {
                $query = $this->getModelClass()::query();
            }

            return $resolveInfo
                ->enhanceBuilder(
                    $query,
                    $this->directiveArgValue('scopes', []),
                    $root,
                    $args,
                    $context,
                    $resolveInfo,
                )
                ->get();
        };
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        $this->validateMutuallyExclusiveArguments(['model', 'builder']);
    }
}

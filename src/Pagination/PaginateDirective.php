<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ComplexityResolverDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class PaginateDirective extends BaseDirective implements FieldResolver, FieldManipulator, ComplexityResolverDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Query multiple entries as a paginated list.
"""
directive @paginate(
  """
  Which pagination style should be used.
  """
  type: PaginateType = PAGINATOR

  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  Mutually exclusive with `builder` and `resolver`.
  """
  model: String

  """
  Point to a function that provides a Query Builder instance.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  Mutually exclusive with `model` and `resolver`.
  """
  builder: String

  """
  Reference a function that resolves the field by directly returning data in a Paginator instance.
  Mutually exclusive with `builder` and `model`.
  Not compatible with `scopes` and builder arguments such as `@eq`.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolver: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Allow clients to query paginated lists without specifying the amount of items.
  Overrules the `pagination.default_count` setting from `lighthouse.php`.
  Setting this to `null` means clients have to explicitly ask for the count.
  """
  defaultCount: Int

  """
  Limit the maximum amount of items that clients can request from paginated lists.
  Overrules the `pagination.max_count` setting from `lighthouse.php`.
  Setting this to `null` means the count is unrestricted.
  """
  maxCount: Int

  """
  Reference a function to customize the complexity score calculation.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  complexityResolver: String
) on FIELD_DEFINITION

"""
Options for the `type` argument of `@paginate`.
"""
enum PaginateType {
  """
  Offset-based pagination, similar to the Laravel default.
  """
  PAGINATOR

  """
  Offset-based pagination like the Laravel "Simple Pagination", which does not count the total number of records.
  """
  SIMPLE

  """
  Cursor-based pagination, compatible with the Relay specification.
  """
  CONNECTION
}
GRAPHQL;
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        $this->validateMutuallyExclusiveArguments(['model', 'builder', 'resolver']);

        $paginationManipulator = new PaginationManipulator($documentAST);

        if ($this->directiveHasArgument('resolver')) {
            // This is done only for validation
            $this->getResolverFromArgument('resolver');
        } elseif ($this->directiveHasArgument('builder')) {
            // This is done only for validation
            $this->getResolverFromArgument('builder');
        } else {
            $paginationManipulator->setModelClass(
                $this->getModelClass(),
            );
        }

        $paginationManipulator->transformToPaginatedField(
            $this->paginationType(),
            $fieldDefinition,
            $parentType,
            $this->defaultCount(),
            $this->paginateMaxCount(),
        );
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Paginator {
            $paginationArgs = PaginationArgs::extractArgs($args, $resolveInfo, $this->paginationType(), $this->paginateMaxCount());

            if ($this->directiveHasArgument('resolver')) {
                $paginator = $this->getResolverFromArgument('resolver')($root, $args, $context, $resolveInfo);
                assert(
                    $paginator instanceof Paginator,
                    "The method referenced by the resolver argument of the @{$this->name()} directive on {$this->nodeName()} must return a Paginator.",
                );

                if ($paginationArgs->first === 0) {
                    if ($paginator instanceof LengthAwarePaginator) {
                        return new ZeroPerPageLengthAwarePaginator($paginator->total(), $paginationArgs->page);
                    }

                    return new ZeroPerPagePaginator($paginationArgs->page);
                }

                return $paginator;
            }

            if ($this->directiveHasArgument('builder')) {
                $query = $this->getResolverFromArgument('builder')($root, $args, $context, $resolveInfo);
                assert(
                    $query instanceof QueryBuilder || $query instanceof EloquentBuilder || $query instanceof ScoutBuilder || $query instanceof Relation,
                    "The method referenced by the builder argument of the @{$this->name()} directive on {$this->nodeName()} must return a Builder or Relation.",
                );
            } else {
                $query = $this->getModelClass()::query();
            }

            $query = $resolveInfo->enhanceBuilder(
                $query,
                $this->directiveArgValue('scopes', []),
                $root,
                $args,
                $context,
                $resolveInfo,
            );

            return $paginationArgs->applyToBuilder($query);
        };
    }

    protected function paginationType(): PaginationType
    {
        return new PaginationType(
            $this->directiveArgValue('type', PaginationType::PAGINATOR),
        );
    }

    protected function defaultCount(): ?int
    {
        return $this->directiveArgValue('defaultCount', config('lighthouse.pagination.default_count'));
    }

    protected function paginateMaxCount(): ?int
    {
        return $this->directiveArgValue('maxCount', config('lighthouse.pagination.max_count'));
    }

    public function complexityResolver(FieldValue $fieldValue): callable
    {
        if ($this->directiveHasArgument('complexityResolver')) {
            return $this->getResolverFromArgument('complexityResolver');
        }

        return static function (int $childrenComplexity, array $args): int {
            /**
             * @see PaginationManipulator::countArgument()
             */
            $expectedNumberOfChildren = $args['first'] ?? 1;

            return
                // Default complexity for this field itself
                1
                // Scale children complexity by the expected number of results
                + $childrenComplexity * $expectedNumberOfChildren;
        };
    }
}

<?php

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class PaginateDirective extends BaseDirective implements FieldResolver, FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Query multiple model entries as a paginated list.
"""
directive @paginate(
  """
  Which pagination style to use.
  Allowed values: `paginator`, `connection`.
  """
  type: String = "paginator"

  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Point to a function that provides a Query Builder instance.
  This replaces the use of a model.
  """
  builder: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Allow clients to query paginated lists without specifying the amount of items.
  Overrules the `pagination.default_count` setting from `lighthouse.php`.
  """
  defaultCount: Int

  """
  Limit the maximum amount of items that clients can request from paginated lists.
  Overrules the `pagination.max_count` setting from `lighthouse.php`.
  """
  maxCount: Int
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode &$parentType): void
    {
        $paginationManipulator = new PaginationManipulator($documentAST);

        if ($this->directiveHasArgument('builder')) {
            // This is done only for validation
            $this->getResolverFromArgument('builder');
        } else {
            $paginationManipulator->setModelClass(
                $this->getModelClass()
            );
        }

        $paginationManipulator
            ->transformToPaginatedField(
                $this->paginationType(),
                $fieldDefinition,
                $parentType,
                $this->directiveArgValue('defaultCount')
                    ?? config('lighthouse.pagination.default_count'),
                $this->paginateMaxCount()
            );
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): LengthAwarePaginator {
                if ($this->directiveHasArgument('builder')) {
                    $builderResolver = $this->getResolverFromArgument('builder');

                    $query = $builderResolver($root, $args, $context, $resolveInfo);
                } else {
                    $query = $this->getModelClass()::query();
                }

                $query = $resolveInfo
                    ->argumentSet
                    ->enhanceBuilder(
                        $query,
                        $this->directiveArgValue('scopes', [])
                    );

                return PaginationArgs
                    ::extractArgs($args, $this->paginationType(), $this->paginateMaxCount())
                    ->applyToBuilder($query);
            }
        );
    }

    protected function paginationType(): PaginationType
    {
        return new PaginationType(
            $this->directiveArgValue('type', PaginationType::TYPE_PAGINATOR)
        );
    }

    /**
     * Get either the specific max or the global setting.
     */
    protected function paginateMaxCount(): ?int
    {
        return $this->directiveArgValue('maxCount')
            ?? config('lighthouse.pagination.max_count');
    }
}

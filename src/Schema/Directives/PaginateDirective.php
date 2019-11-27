<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Pagination\PaginationManipulator;
use Nuwave\Lighthouse\Pagination\PaginationType;
use Nuwave\Lighthouse\Pagination\PaginationUtils;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class PaginateDirective extends BaseDirective implements FieldResolver, FieldManipulator, DefinedDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'paginate';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Query multiple entries as a paginated list.
"""
directive @paginate(
  """
  Which pagination style to use.
  Allowed values: paginator, connection.
  """
  type: String = "paginator"

  """
  Specify the class name of the model to use.
  This is only needed when the default model resolution does not work.
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
  Overwrite the paginate_max_count setting value to limit the
  amount of items that a user can request per page.
  """
  maxCount: Int

  """
  Use a default value for the amount of returned items
  in case the client does not request it explicitly
  """
  defaultCount: Int
) on FIELD_DEFINITION
SDL;
    }

    /**
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @return void
     */
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
                $this->directiveArgValue('defaultCount'),
                $this->paginateMaxCount()
            );
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): LengthAwarePaginator {
                /** @var int $first */
                /** @var int $page */
                [$first, $page] = PaginationUtils::extractArgs($args, $this->paginationType(), $this->paginateMaxCount());

                if ($this->directiveHasArgument('builder')) {
                    $query = call_user_func(
                        $this->getResolverFromArgument('builder'),
                        $root,
                        $args,
                        $context,
                        $resolveInfo
                    );
                } else {
                    $query = $this->getModelClass()::query();
                }

                $query = $resolveInfo
                    ->argumentSet
                    ->enhanceBuilder(
                        $query,
                        $this->directiveArgValue('scopes', [])
                    );

                if ($query instanceof ScoutBuilder) {
                    return $query->paginate($first, 'page', $page);
                }

                return $query->paginate($first, ['*'], 'page', $page);
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
     *
     * @return int|null
     */
    protected function paginateMaxCount(): ?int
    {
        return $this->directiveArgValue('maxCount')
            ?? config('lighthouse.paginate_max_count');
    }
}

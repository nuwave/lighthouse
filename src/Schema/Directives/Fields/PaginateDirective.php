<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Support\Str;
use GraphQL\Type\Definition\ResolveInfo;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Execution\Utils\Cursor;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\Utils\Pagination;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class PaginateDirective extends BaseDirective implements FieldResolver, FieldManipulator
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

    /**
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $current
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current): DocumentAST
    {
        return PaginationManipulator::transformToPaginatedField(
            $this->getPaginationType(),
            $fieldDefinition,
            $parentType,
            $current,
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
        switch ($this->getPaginationType()) {
            case PaginationManipulator::PAGINATION_TYPE_CONNECTION:
                return $this->connectionTypeResolver($fieldValue);
            case PaginationManipulator::PAGINATION_TYPE_PAGINATOR:
            default:
                return $this->paginatorTypeResolver($fieldValue);
        }
    }

    /**
     * @return string
     */
    protected function getPaginationType(): string
    {
        return PaginationManipulator::assertValidPaginationType(
            $this->directiveArgValue('type', PaginationManipulator::PAGINATION_TYPE_PAGINATOR)
        );
    }

    /**
     * Create a paginator resolver.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $value
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    protected function paginatorTypeResolver(FieldValue $value): FieldValue
    {
        return $value->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): LengthAwarePaginator {
                return $this->getPaginatedResults(
                    $root,
                    $args,
                    $context,
                    $resolveInfo,
                    $args['page'] ?? 1,
                    $args['count']
                );
            }
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

    /**
     * Create a connection resolver.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $value
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    protected function connectionTypeResolver(FieldValue $value): FieldValue
    {
        return $value->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): LengthAwarePaginator {
                $first = $args['first'];

                return $this->getPaginatedResults(
                    $root,
                    $args,
                    $context,
                    $resolveInfo,
                    Pagination::calculateCurrentPage(
                        $first,
                        Cursor::decode($args)
                    ),
                    $first
                );
            }
        );
    }

    /**
     * @param  null  $root
     * @param  mixed[]  $args
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @param  int  $page
     * @param  int  $first
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function getPaginatedResults($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo, int $page, int $first): LengthAwarePaginator
    {
        Pagination::throwIfPaginateMaxCountExceeded(
            $this->paginateMaxCount(),
            $first
        );

        if ($this->directiveHasArgument('builder')) {
            $query = call_user_func(
                $this->getResolverFromArgument('builder'),
                $root,
                $args,
                $context,
                $resolveInfo
            );
        } else {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = $this->getPaginatorModel();
            $query = $model::query();
        }

        $query = $resolveInfo
            ->builder
            ->addScopes(
                $this->directiveArgValue('scopes', [])
            )
            ->apply(
                $query,
                $args
            );

        if ($query instanceof ScoutBuilder) {
            return $query->paginate($first, 'page', $page);
        }

        return $query->paginate($first, ['*'], 'page', $page);
    }

    /**
     * Get the model class from the `model` argument of the field.
     *
     * This works differently as in other directives, so we define a separate function for it.
     *
     * @return string
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function getPaginatorModel(): string
    {
        $model = $this->directiveArgValue('model');

        // Fallback to using information from the schema definition as the model name
        if (! $model) {
            $model = ASTHelper::getUnderlyingTypeName($this->definitionNode);

            // Cut the added type suffix to get the base model class name
            $model = Str::before($model, 'Paginator');
            $model = Str::before($model, 'Connection');
        }

        if (! $model) {
            throw new DirectiveException(
                "A `model` argument must be assigned to the '{$this->name()}' directive on '{$this->definitionNode->name->value}"
            );
        }

        return $this->namespaceModelClass($model);
    }
}

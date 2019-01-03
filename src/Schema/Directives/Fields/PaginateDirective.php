<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Execution\QueryFilter;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Execution\Utils\Cursor;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\Utils\Pagination;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
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
     * @param FieldDefinitionNode      $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST              $current
     *
     * @return DocumentAST
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current): DocumentAST
    {
        return PaginationManipulator::transformToPaginatedField(
            $this->getPaginationType(),
            $fieldDefinition,
            $parentType,
            $current,
            $this->directiveArgValue('defaultCount')
        );
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $fieldValue
     *
     * @return FieldValue
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
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    protected function paginatorTypeResolver(FieldValue $value): FieldValue
    {
        return $value->setResolver(
            function ($root, array $args): LengthAwarePaginator {
                $first = $args['count'];
                $page = $args['page'] ?? 1;

                return $this->getPaginatedResults(func_get_args(), $page, $first);
            }
        );
    }

    /**
     * Create a connection resolver.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    protected function connectionTypeResolver(FieldValue $value): FieldValue
    {
        return $value->setResolver(
            function ($root, array $args): LengthAwarePaginator {
                $first = $args['first'];
                $page = Pagination::calculateCurrentPage(
                    $first,
                    Cursor::decode($args)
                );

                return $this->getPaginatedResults(func_get_args(), $page, $first);
            }
        );
    }

    /**
     * @param array $resolveArgs
     * @param int   $page
     * @param int   $first
     *
     * @return LengthAwarePaginator
     */
    protected function getPaginatedResults(array $resolveArgs, int $page, int $first): LengthAwarePaginator
    {
        if ($this->directiveHasArgument('builder')) {
            $query = call_user_func_array(
                $this->getResolverFromArgument('builder'),
                $resolveArgs
            );
        } else {
            /** @var Model $model */
            $model = $this->getPaginatorModel();
            $query = $model::query();
        }

        $query = QueryFilter::apply(
            $query,
            $resolveArgs[1],
            $this->directiveArgValue('scopes', []),
            $resolveArgs[3]
        );

        return $query->paginate($first, ['*'], 'page', $page);
    }

    /**
     * Get the model class from the `model` argument of the field.
     *
     * This works differently as in other directives, so we define a seperate function for it.
     *
     * @throws DirectiveException
     *
     * @return string
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

        return $this->namespaceClassName(
            $model,
            (array) config('lighthouse.namespaces.models')
        );
    }
}

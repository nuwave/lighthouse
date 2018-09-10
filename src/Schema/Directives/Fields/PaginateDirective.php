<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Pagination\Paginator;
use Nuwave\Lighthouse\Execution\QueryUtils;
use Nuwave\Lighthouse\Execution\Utils\Cursor;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\Utils\Pagination;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginateDirective extends PaginationManipulator implements FieldResolver, FieldManipulator
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'paginate';
    }

    /**
     * @param FieldDefinitionNode      $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST              $current
     * @param DocumentAST              $original
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current, DocumentAST $original)
    {
        switch ($this->getPaginationType()) {
            case self::PAGINATION_TYPE_CONNECTION:
                return $this->registerConnection($fieldDefinition, $parentType, $current, $original);
            case self::PAGINATION_TYPE_PAGINATOR:
            default:
                return $this->registerPaginator($fieldDefinition, $parentType, $current, $original);
        }
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @throws DirectiveException
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $value)
    {
        $query = $this->getModelClass()::query();

        switch ($this->getPaginationType()) {
            case self::PAGINATION_TYPE_CONNECTION:
                return $this->connectionTypeResolver($value, $query);
            case self::PAGINATION_TYPE_PAGINATOR:
            default:
                return $this->paginatorTypeResolver($value, $query);
        }
    }

    /**
     * @return string
     * @throws DirectiveException
     */
    protected function getPaginationType()
    {
        $paginationType = $this->directiveArgValue('type', self::PAGINATION_TYPE_PAGINATOR);

        $paginationType = $this->convertAliasToPaginationType($paginationType);
        if (!$this->isValidPaginationType($paginationType)) {
            $fieldName = $this->fieldDefinition->name->value;
            $directiveName = self::name();
            throw new DirectiveException("'$paginationType' is not a valid pagination type. Field: '$fieldName', Directive: '$directiveName'");
        }

        return $paginationType;
    }

    /**
     * Create a paginator resolver.
     *
     * @param FieldValue $value
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     *
     * @return FieldValue
     */
    protected function paginatorTypeResolver(FieldValue $value, $query)
    {
        return $value->setResolver(function ($root, array $args) use ($query) {
            $first = $args['count'];
            $page = array_get($args, 'page', 1);

            return $this->getPaginatatedResults($args, $query, $page, $first);
        });
    }

    /**
     * Create a connection resolver.
     *
     * @param FieldValue $value
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     *
     * @return FieldValue
     */
    protected function connectionTypeResolver(FieldValue $value, $query)
    {
        return $value->setResolver(function ($root, array $args) use ($query) {
            $first = $args['first'];
            $page = Pagination::calculateCurrentPage(
                $first,
                Cursor::decode($args)
            );

            return $this->getPaginatatedResults($args, $query, $page, $first);
        });
    }


    /**
     * @param array $args
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param int $page
     * @param int $first
     * @return LengthAwarePaginator
     */
    protected function getPaginatatedResults(array $args, $query, int $page, int $first): LengthAwarePaginator
    {
        $query = QueryUtils::applyFilters($query, $args);
        $query = QueryUtils::applyScopes($query, $args, $this->directiveArgValue('scopes', []));

        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });

        return $query->paginate($first);
    }
}

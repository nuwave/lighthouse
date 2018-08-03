<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Pagination\Paginator;
use Nuwave\Lighthouse\Execution\QueryUtils;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Schema\Execution\Utils\GlobalIdUtil;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

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
        $paginationType = $this->getPaginationType();

        $model = $this->getModelClass();

        switch ($paginationType) {
            case self::PAGINATION_TYPE_CONNECTION:
                return $this->connectionTypeResolver($value, $model);
            case self::PAGINATION_TYPE_PAGINATOR:
                return $this->paginatorTypeResolver($value, $model);
        }
    }

    /**
     * @throws DirectiveException
     *
     * @return string
     */
    protected function getPaginationType()
    {
        $paginationType = $this->directiveArgValue('type', self::PAGINATION_TYPE_PAGINATOR);

        $paginationType = $this->convertAliasToPaginationType($paginationType);
        if (! $this->isValidPaginationType($paginationType)) {
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
     * @param string     $model
     *
     * @return FieldValue
     */
    protected function paginatorTypeResolver(FieldValue $value, $model)
    {
        return $value->setResolver(function ($root, array $args) use ($model) {
            $first = data_get($args, 'count', 15);
            $page = data_get($args, 'page', 1);

            $query = QueryUtils::applyFilters($model::query(), $args);
            $query = QueryUtils::applyScopes($query, $args, $this->directiveArgValue('scopes', []));

            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });

            return $query->paginate($first);
        });
    }

    /**
     * Create a connection resolver.
     *
     * @param FieldValue $value
     * @param string     $model
     *
     * @return FieldValue
     */
    protected function connectionTypeResolver(FieldValue $value, $model)
    {
        return $value->setResolver(function ($root, array $args) use ($model, $value) {
            $first = data_get($args, 'first', 15);
            $after = GlobalIdUtil::decodeCursor($args);
            $page = $first && $after ? floor(($first + $after) / $first) : 1;

            $query = QueryUtils::applyFilters($model::query(), $args);
            $query = QueryUtils::applyScopes($query, $args, $this->directiveArgValue('scopes', []));

            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });

            return $query->paginate($first);
        });
    }
}

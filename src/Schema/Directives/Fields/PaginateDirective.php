<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Pagination\Paginator;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\PaginatorCreatingDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandleQueries;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class PaginateDirective extends PaginatorCreatingDirective implements FieldResolver, FieldManipulator
{
    use HandlesGlobalId, HandlesQueryFilter, HandleQueries;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
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
        switch ($this->getPaginationType($fieldDefinition)) {
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
        $paginationType = $this->getPaginationType($value->getField());

        $model = $this->getModelClass($value);

        switch ($paginationType) {
            case self::PAGINATION_TYPE_CONNECTION:
                $resolver = $this->connectionTypeResolver($value, $model);
                break;
            case self::PAGINATION_TYPE_PAGINATOR:
                $resolver = $this->paginatorTypeResolver($value, $model);
                break;
        }

        return $value->setResolver($resolver);
    }

    /**
     * @param FieldDefinitionNode $field
     *
     * @throws DirectiveException
     *
     * @return string
     */
    protected function getPaginationType(FieldDefinitionNode $field)
    {
        $paginationType = $this->directiveArgValue(
            $this->fieldDirective($field, self::name()),
            'type',
            self::PAGINATION_TYPE_PAGINATOR
        );

        $paginationType = $this->convertAliasToPaginationType($paginationType);
        if (! $this->isValidPaginationType($paginationType)) {
            $fieldName = $field->name->value;
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
     * @return \Closure
     */
    protected function paginatorTypeResolver(FieldValue $value, $model)
    {
        return function ($root, array $args) use ($model, $value) {
            $first = data_get($args, 'count', 15);
            $page = data_get($args, 'page', 1);

            $query = $this->applyFilters($model::query(), $args);
            $query = $this->applyScopes($query, $args, $value);

            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });

            return $query->paginate($first);
        };
    }

    /**
     * Create a connection resolver.
     *
     * @param FieldValue $value
     * @param string     $model
     *
     * @return \Closure
     */
    protected function connectionTypeResolver(FieldValue $value, $model)
    {
        return function ($root, array $args) use ($model, $value) {
            $first = data_get($args, 'first', 15);
            $after = $this->decodeCursor($args);
            $page = $first && $after ? floor(($first + $after) / $first) : 1;

            $query = $this->applyFilters($model::query(), $args);
            $query = $this->applyScopes($query, $args, $value);

            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });

            return $query->paginate($first);
        };
    }
}

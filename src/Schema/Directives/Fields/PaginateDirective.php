<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Pagination\Paginator;
use Nuwave\Lighthouse\Schema\Directives\CreatesPaginators;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandleQueries;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class PaginateDirective implements FieldResolver, FieldManipulator
{
    use CreatesPaginators, HandlesGlobalId, HandlesQueryFilter, HandleQueries;

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
     * @param FieldDefinitionNode $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current, DocumentAST $original)
    {
        $paginatorType = $this->directiveArgValue(
            $this->fieldDirective($fieldDefinition, self::name()),
            'type',
            'paginator'
        );

        switch ($paginatorType) {
            case 'relay':
            case 'connection':
                return $this->registerConnection($fieldDefinition, $parentType, $current, $original);
            case 'paginator':
            default:
                return $this->registerPaginator($fieldDefinition, $parentType, $current, $original);
        }
    }

    protected function getPaginatorType(FieldDefinitionNode $field)
    {
        return $this->directiveArgValue(
            $this->fieldDirective($field, self::name()),
            'type',
            'paginator'
        );
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
        $type = $this->getPaginatorType($value->getField());

        $model = $this->getModelClass($value);

        $resolver = in_array($type, ['relay', 'connection'])
            ? $this->connectionTypeResolver($value, $model)
            : $this->paginatorTypeResolver($value, $model);

        return $value->setResolver($resolver);
    }

    /**
     * Create a paginator resolver.
     *
     * @param FieldValue $value
     * @param string $model
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
     * @param string $model
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

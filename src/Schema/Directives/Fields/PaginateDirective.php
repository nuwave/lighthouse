<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Execution\QueryUtils;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Exceptions\DirectiveException;


class PaginateDirective extends PaginationManipulator implements FieldResolver, FieldManipulator
{
    use HandlesGlobalId;

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
     * @param FieldDefinitionNode $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @throws \Exception
     * @throws DirectiveException
     *
     * @return DocumentAST
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current, DocumentAST $original): DocumentAST
    {
        switch ($this->getPaginationType()) {
            case self::PAGINATION_TYPE_CONNECTION:
                return $this->registerConnection($fieldDefinition, $parentType, $current, $original);
            case self::PAGINATION_TYPE_PAGINATOR:
                return $this->registerPaginator($fieldDefinition, $parentType, $current, $original);
            default:
                return $this->registerPaginator($fieldDefinition, $parentType, $current, $original);
        }
    }

    /**
     * @throws DirectiveException
     *
     * @return string
     */
    protected function getPaginationType(): string
    {
        $paginationType = $this->directiveArgValue('type', self::PAGINATION_TYPE_PAGINATOR);

        $paginationType = $this->convertAliasToPaginationType($paginationType);
        if (!$this->isValidPaginationType($paginationType)) {
            $fieldName = $this->definitionNode->name->value;
            $directiveName = $this->name();
            throw new DirectiveException("'$paginationType' is not a valid pagination type. Field: '$fieldName', Directive: '$directiveName'");
        }

        return $paginationType;
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @throws \Exception
     * @throws DirectiveException
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $value): FieldValue
    {
        $paginationType = $this->getPaginationType();
        $resolver = null;
        $modelClass = null;

        try {
            $resolver = $this->getResolver();
        } catch (DirectiveException $e) {
            $modelClass = $this->getModelClass();
        }

        switch ($paginationType) {
            case self::PAGINATION_TYPE_CONNECTION:
                return $this->connectionTypeResolver($value, $resolver, $modelClass);
            case self::PAGINATION_TYPE_PAGINATOR:
                return $this->paginatorTypeResolver($value, $resolver, $modelClass);
            default:
                return $this->paginatorTypeResolver($value, $resolver, $modelClass);
        }
    }

    /**
     * Create a connection resolver.
     *
     * @param FieldValue $value
     * @param \Closure|null $resolver
     * @param null|string $modelClass
     *
     * @return FieldValue
     */
    protected function connectionTypeResolver(FieldValue $value, ?\Closure $resolver, ?string $modelClass): FieldValue
    {
        return $value->setResolver(function ($root, array $args, Context $context, ResolveInfo $info) use ($resolver, $modelClass) {
            $first = data_get($args, 'first', 15);
            $after = $this->decodeCursor($args);
            $page = $first && $after ? floor(($first + $after) / $first) : 1;

            $this->setCurrentPageResolver($page);

            return $this->getPaginator($first, $resolver, $modelClass, \func_get_args());
        });
    }

    /**
     * Create a paginator resolver.
     *
     * @param FieldValue $value
     * @param \Closure|null $resolver
     * @param null|string $modelClass
     *
     * @return FieldValue
     */
    protected function paginatorTypeResolver(FieldValue $value, ?\Closure $resolver, ?string $modelClass): FieldValue
    {
        return $value->setResolver(function ($root, array $args, Context $context, ResolveInfo $info) use ($resolver, $modelClass) {
            $count = data_get($args, 'count', 15);
            $page = data_get($args, 'page', 1);

            $this->setCurrentPageResolver($page);

            return $this->getPaginator($count, $resolver, $modelClass, \func_get_args());
        });
    }

    /**
     * Set the current page to the given page number.
     *
     * @param int $page
     */
    protected function setCurrentPageResolver(int $page): void
    {
        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });
    }

    /**
     * @param int $perPage
     * @param \Closure|null $resolver
     * @param null|string $modelClass
     * @param array $resolverArgs
     *
     * @throws DirectiveException
     *
     * @return LengthAwarePaginator
     */
    protected function getPaginator(int $perPage, ?\Closure $resolver, ?string $modelClass, array $resolverArgs): LengthAwarePaginator
    {
        if ($resolver) {
            $query = \call_user_func_array($resolver, $resolverArgs);
            if ( ! ($query instanceof Builder)) {
                throw new DirectiveException('The returned value of paginate resolver must be an instance of ' . Builder::class);
            }
        } else {
            $query = $modelClass::query();
        }

        $args = $resolverArgs[1];
        $query = QueryUtils::applyFilters($query, $args);
        $query = QueryUtils::applyScopes($query, $args, $this->directiveArgValue('scopes', []));

        return $query->paginate($perPage);
    }
}

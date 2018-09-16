<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
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
    public function name(): string
    {
        return 'paginate';
    }

    /**
     * @param FieldDefinitionNode      $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST              $current
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current): DocumentAST
    {
        switch ($this->getPaginationType()) {
            case self::PAGINATION_TYPE_CONNECTION:
                return $this->registerConnection($fieldDefinition, $parentType, $current);
            case self::PAGINATION_TYPE_PAGINATOR:
            default:
                return $this->registerPaginator($fieldDefinition, $parentType, $current);
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
    public function resolveField(FieldValue $value): FieldValue
    {
        switch ($this->getPaginationType()) {
            case self::PAGINATION_TYPE_CONNECTION:
                return $this->connectionTypeResolver($value);
            case self::PAGINATION_TYPE_PAGINATOR:
            default:
                return $this->paginatorTypeResolver($value);
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
     *
     * @return FieldValue
     */
    protected function paginatorTypeResolver(FieldValue $value): FieldValue
    {
        return $value->setResolver(
            function ($root, array $args){
                $first = $args['count'];
                $page = array_get($args, 'page', 1);

                return $this->getPaginatatedResults(func_get_args(), $page, $first);
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
            function ($root, array $args) {
                $first = $args['first'];
                $page = Pagination::calculateCurrentPage(
                    $first,
                    Cursor::decode($args)
                );

                return $this->getPaginatatedResults(func_get_args(), $page, $first);
            }
        );
    }


    /**
     * @param array $resolveArgs
     * @param int $page
     * @param int $first
     *
     * @throws DirectiveException
     *
     * @return LengthAwarePaginator
     */
    protected function getPaginatatedResults(array $resolveArgs, int $page, int $first): LengthAwarePaginator
    {
        if($this->directiveHasArgument('builder')){
            $query = call_user_func_array(
                $this->getMethodArgument('builder'),
                $resolveArgs
            );
        } else {
            /** @var Model $model */
            $model = $this->getModelClass();
            $query = $model::query();
        }

        $args = $resolveArgs[1];
        $query = QueryUtils::applyFilters($query, $args);
        $query = QueryUtils::applyScopes($query, $args, $this->directiveArgValue('scopes', []));

        return $query->paginate($first, ['*'], 'page', $page);
    }
}

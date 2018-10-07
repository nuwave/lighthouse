<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields\Concerns;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Directives\Fields\PaginationManipulator;

trait RegisterPaginationType
{
    /**
     * @param FieldDefinitionNode $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST $current
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current): DocumentAST
    {
        $paginationType = $this->getPaginationType();

        switch ($paginationType) {
            case PaginationManipulator::PAGINATION_TYPE_PAGINATOR:
                return PaginationManipulator::registerPaginator($fieldDefinition, $parentType, $current);
            case PaginationManipulator::PAGINATION_TYPE_CONNECTION:
                return PaginationManipulator::registerConnection($fieldDefinition, $parentType, $current);
            default:
                // Leave the field as-is when no pagination is requested
                return $current;
        }
    }

    /**
     * @throws \Exception
     * @throws DirectiveException
     *
     * @return string
     */
    protected function getPaginationType(): string
    {
        $paginationType = $this->directiveArgValue('type', 'default');

        if ('default' === $paginationType) {
            return $paginationType;
        }

        $paginationType = PaginationManipulator::convertAliasToPaginationType($paginationType);

        if ( ! PaginationManipulator::isValidPaginationType($paginationType)) {
            $fieldName = $this->definitionNode->name->value;
            $directiveName = $this->name();
            throw new DirectiveException("'$paginationType' is not a valid pagination type. Field: '$fieldName', Directive: '$directiveName'");
        }

        return $paginationType;
    }

}
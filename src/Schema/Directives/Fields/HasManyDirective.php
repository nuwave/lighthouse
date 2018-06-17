<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\DataLoader\Loaders\HasManyLoader;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class HasManyDirective extends PaginationManipulator implements FieldResolver, FieldManipulator
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'hasMany';
    }

    /**
     * @param FieldDefinitionNode      $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST              $current
     * @param DocumentAST              $original
     *
     * @throws DirectiveException
     *
     * @return DocumentAST
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current, DocumentAST $original)
    {
        $paginationType = $this->getResolverType();

        switch ($paginationType) {
            case self::PAGINATION_TYPE_PAGINATOR:
                return $this->registerPaginator($fieldDefinition, $parentType, $current, $original);
            case self::PAGINATION_TYPE_CONNECTION:
                return $this->registerConnection($fieldDefinition, $parentType, $current, $original);
            default:
                // Leave the field as-is when no pagination is requested
                return $current;
        }
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $value)
    {
        $relation = $this->associatedArgValue('relation', $value->getFieldName());
        $type = $this->getResolverType();
        $scopes = $this->associatedArgValue('scopes', []);

        return $value->setResolver(function ($parent, array $args, $context = null, ResolveInfo $info = null) use ($relation, $scopes, $type) {
            return graphql()->batch(HasManyLoader::class, $parent->getKey(), array_merge(
                compact('relation', 'parent', 'args', 'scopes'),
                ['type' => $type]
            ), HasManyLoader::key($parent, $relation, $info));
        });
    }

    /**
     * @throws DirectiveException
     *
     * @return string
     */
    protected function getResolverType()
    {
        $paginationType = $this->associatedArgValue('type', 'default');

        if ('default' === $paginationType) {
            return $paginationType;
        }

        $paginationType = $this->convertAliasToPaginationType($paginationType);

        if (!$this->isValidPaginationType($paginationType)) {
            $fieldName = $this->fieldDefinition->name->value;
            $directiveName = self::name();
            throw new DirectiveException("'$paginationType' is not a valid pagination type. Field: '$fieldName', Directive: '$directiveName'");
        }

        return $paginationType;
    }
}

<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\DataLoader\Loaders\HasManyLoader;

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
     * @param FieldDefinitionNode $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @throws DirectiveException
     * @throws \Exception
     *
     * @return DocumentAST
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current, DocumentAST $original)
    {
        $paginationType = $this->getPaginationType();

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
        return $value->setResolver(
            function (Model $parent, array $resolveArgs, $context = null, ResolveInfo $resolveInfo = null) {
                /** @var HasManyLoader $hasManyLoader */
                $hasManyLoader = graphql()->batchLoader(
                    HasManyLoader::class,
                    $resolveInfo->path,
                    [
                        'relation' => $this->directiveArgValue('relation', $this->definitionNode->name->value),
                        'resolveArgs' => $resolveArgs,
                        'scopes' => $this->directiveArgValue('scopes', []),
                        'paginationType' => $this->getPaginationType()
                    ]
                );

                return $hasManyLoader->load($parent->getKey(), ['parent' => $parent]);
            }
        );
    }

    /**
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

        $paginationType = $this->convertAliasToPaginationType($paginationType);

        if (!$this->isValidPaginationType($paginationType)) {
            $fieldName = $this->definitionNode->name->value;
            $directiveName = self::name();
            throw new DirectiveException("'$paginationType' is not a valid pagination type. Field: '$fieldName', Directive: '$directiveName'");
        }

        return $paginationType;
    }
}

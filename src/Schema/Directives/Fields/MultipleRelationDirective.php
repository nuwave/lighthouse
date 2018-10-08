<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Execution\DataLoader\MultipleRelationLoader;

abstract class MultipleRelationDirective extends RelationDirective
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
        $paginationType = $this->directiveArgValue('type');
        
        // We default to not changing the field if no pagination type is set explicitly.
        // This makes sense for relations, as there should not be too many entries.
        if(! $paginationType) {
            return $current;
        }
        
        return PaginationManipulator::transformToPaginatedField($paginationType, $fieldDefinition, $parentType, $current);
    }

    protected function getLoaderClassName(): string
    {
        return MultipleRelationLoader::class;
    }

    /**
     * @param Model $parent
     * @param array $resolveArgs
     * @param null $context
     * @param ResolveInfo $resolveInfo
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function getLoaderConstructorArguments(Model $parent, array $resolveArgs, $context, ResolveInfo $resolveInfo): array
    {
        $constructorArgs =  [
            'scopes' => $this->directiveArgValue('scopes', []),
            'resolveArgs' => $resolveArgs,
            'relationName' => $this->directiveArgValue('relation', $this->definitionNode->name->value),
        ];
        
        if($paginationType = $this->directiveArgValue('type')){
            $constructorArgs += ['paginationType' => PaginationManipulator::assertValidPaginationType($paginationType)];
        }
        
        return $constructorArgs;
    }
}

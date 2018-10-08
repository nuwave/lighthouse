<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\DataLoader\SingleRelationLoader;

abstract class SingleRelationDirective extends RelationDirective
{
    /**
     * The class name of the concrete BatchLoader to instantiate.
     *
     * @return string
     */
    protected function getLoaderClassName(): string
    {
        return SingleRelationLoader::class;
    }

    /**
     * Those arguments are passed to the constructor of the new BatchLoader instance.
     *
     * @param Model $parent
     * @param array $resolveArgs
     * @param null $context
     * @param ResolveInfo|null $resolveInfo
     *
     * @return array
     */
    protected function getLoaderConstructorArguments(Model $parent, array $resolveArgs, $context, ResolveInfo $resolveInfo): array
    {
        $relationName = $this->directiveArgValue('relation', $this->definitionNode->name->value);

        return [
            'resolveArgs'  => $resolveArgs,
            'relationName' => $relationName,
        ];
    }
}

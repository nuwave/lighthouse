<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\DataLoader\Loaders\SingleRelationLoader;

abstract class SingleRelationDirective extends RelationDirective
{
    use Concerns\RegisterPaginationType;

    /**
     * Name of the directive.
     *
     * @return string
     */
    abstract public function name(): string;

    protected function getLoaderClassName(): string
    {
        return SingleRelationLoader::class;
    }

    /**
     * @param Model $parent
     * @param array $resolveArgs
     * @param null $context
     * @param ResolveInfo|null $resolveInfo
     *
     * @return array
     * @throws \Exception
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
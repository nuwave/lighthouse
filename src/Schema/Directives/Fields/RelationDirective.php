<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;


use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

abstract class RelationDirective extends BaseDirective
{
    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     * @throws \Exception
     */
    public function resolveField(FieldValue $value): FieldValue
    {
        return $value->setResolver(function (Model $parent, array $args, $context = null, ResolveInfo $resolveInfo) {
            return $this->getBatchLoader($parent, $args, $context, $resolveInfo)->load(
                $parent->getKey(),
                ['parent' => $parent]
            );
        });
    }

    /**
     * @param Model $parent
     * @param array $resolveArgs
     * @param $context
     * @param ResolveInfo $resolveInfo
     *
     * @return BatchLoader
     * @throws \Exception
     */
    protected function getBatchLoader(Model $parent, array $resolveArgs, $context, ResolveInfo $resolveInfo): BatchLoader
    {
        return graphql()->batchLoader(
            $this->getLoaderClassName(),
            $resolveInfo->path,
            $this->getLoaderConstructorArguments($parent, $resolveArgs, $context, $resolveInfo)
        );
    }

    /**
     * @return string
     */
    abstract protected function getLoaderClassName(): string;

    /**
     * @param Model $parent
     * @param array $resolveArgs
     * @param $context
     * @param ResolveInfo $resolveInfo
     *
     * @return array
     */
    abstract protected function getLoaderConstructorArguments(Model $parent, array $resolveArgs, $context, ResolveInfo $resolveInfo): array;
}
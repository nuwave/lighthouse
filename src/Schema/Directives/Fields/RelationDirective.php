<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
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
        return $value->setResolver(
            function (Model $parent, array $args, $context, ResolveInfo $resolveInfo) {
                return BatchLoader::instance(
                    $this->getLoaderClassName(),
                    $resolveInfo->path,
                    $this->getLoaderConstructorArguments($parent, $args, $context, $resolveInfo)
                )->load(
                    $parent->getKey(),
                    ['parent' => $parent]
                );
            }
        );
    }

    /**
     * The class name of the concrete BatchLoader to instantiate.
     *
     * @return string
     */
    abstract protected function getLoaderClassName(): string;

    /**
     * Those arguments are passed to the constructor of the new BatchLoader instance.
     *
     * @param Model $parent
     * @param array $resolveArgs
     * @param $context
     * @param ResolveInfo $resolveInfo
     *
     * @return array
     */
    abstract protected function getLoaderConstructorArguments(Model $parent, array $resolveArgs, $context, ResolveInfo $resolveInfo): array;
}

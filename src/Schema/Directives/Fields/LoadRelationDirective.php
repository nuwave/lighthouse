<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Execution\DataLoader\RelationBatchLoader;

class LoadRelationDirective extends RelationDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'loadRelation';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     * @param \Closure   $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next)
    {
        $resolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function (Model $parent, array $resolveArgs, $context = null, $resolveInfo = null) use ($resolver) {
                    $loader = BatchLoader::instance(
                        RelationBatchLoader::class,
                        \array_slice($resolveInfo->path, 0, \count($resolveInfo->path) - 2),
                        $this->getLoaderConstructorArguments($parent, $resolveArgs, $context, $resolveInfo)
                    );

                    return new Deferred(function () use ($loader, $resolver, $parent, $resolveArgs, $context, $resolveInfo) {
                        return $loader->load(
                            $parent->getKey(),
                            ['parent' => $parent]
                        )->then(
                            function () use ($resolver, $parent, $resolveArgs, $context, $resolveInfo) {
                                return $resolver($parent, $resolveArgs, $context, $resolveInfo);
                            }
                        );
                    });
                }
            )
        );
    }
}


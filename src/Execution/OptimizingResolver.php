<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class OptimizingResolver
{
    /**
     * @var callable
     */
    private $resolver;

    /**
     * @var array<FieldMiddleware>
     */
    private $fieldMiddleware;

    public function __construct(callable $resolver, array $fieldMiddleware)
    {
        $this->resolver = $resolver;
        $this->fieldMiddleware = $fieldMiddleware;
    }

    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        [$args, $resolveInfo] = $this->transformArgs($args, $resolveInfo);
    }

    protected function transformArgs(array $args, ResolveInfo $resolveInfo)
    {
        // check if args were transformed
        // run necessary field middleware
        $resolveInfo->argumentSet = $this->argumentSetFactory->fromResolveInfo($args, $resolveInfo);
    }

    protected function applyFieldMiddleware()
    {
        $resolverWithMiddleware = $this->pipeline
            ->send($fieldValue)
            ->through($fieldMiddleware)
            ->via('handleField')
            // TODO replace when we cut support for Laravel 5.6
            //->thenReturn()
            ->then(static function (FieldValue $fieldValue): FieldValue {
                return $fieldValue;
            })
            ->getResolver();
    }
}

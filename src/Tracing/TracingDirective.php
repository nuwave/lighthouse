<?php

namespace Nuwave\Lighthouse\Tracing;

use Closure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class TracingDirective extends BaseDirective implements FieldMiddleware, DefinedDirective
{
    /**
     * @var \Nuwave\Lighthouse\Tracing\Tracing
     */
    protected $tracing;

    public function __construct(Tracing $tracing)
    {
        $this->tracing = $tracing;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Do not use this directive directly, it is automatically added to the schema
when using the tracing extension.
"""
directive @tracing on FIELD_DEFINITION
SDL;
    }

    /**
     * Resolve the field directive.
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $fieldValue = $next($fieldValue);

        $resolver = $fieldValue->getResolver();

        return $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            $start = $this->tracing->getTime();

            $result = $resolver($root, $args, $context, $resolveInfo);

            $end = $this->tracing->getTime();

            if ($result instanceof Deferred) {
                $result->then(function () use ($resolveInfo, $start, $end): void {
                    $this->tracing->record($resolveInfo, $start, $end);
                });
            } else {
                $this->tracing->record($resolveInfo, $start, $end);
            }

            return $result;
        });
    }
}

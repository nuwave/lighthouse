<?php

namespace Nuwave\Lighthouse\Tracing;

use Closure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class TracingDirective extends BaseDirective implements FieldMiddleware
{
    const NAME = 'tracing';

    /**
     * @var \Nuwave\Lighthouse\Tracing\Tracing
     */
    protected $tracing;

    /**
     * TracingDirective constructor.
     *
     * @param  \Nuwave\Lighthouse\Tracing\Tracing  $tracing
     * @return void
     */
    public function __construct(Tracing $tracing)
    {
        $this->tracing = $tracing;
    }

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return self::NAME;
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
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

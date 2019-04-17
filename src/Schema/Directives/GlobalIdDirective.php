<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;

class GlobalIdDirective extends BaseDirective implements FieldMiddleware, ArgTransformerDirective
{
    /**
     * The GlobalId resolver.
     *
     * @var \Nuwave\Lighthouse\Support\Contracts\GlobalId
     */
    protected $globalId;

    /**
     * GlobalIdDirective constructor.
     *
     * @param  \Nuwave\Lighthouse\Support\Contracts\GlobalId  $globalId
     * @return void
     */
    public function __construct(GlobalId $globalId)
    {
        $this->globalId = $globalId;
    }

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'globalId';
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $value
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $value, Closure $next): FieldValue
    {
        $type = $value->getParentName();
        $resolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function () use ($type, $resolver) {
                    $resolvedValue = call_user_func_array($resolver, func_get_args());

                    return $this->globalId->encode(
                        $type,
                        $resolvedValue
                    );
                }
            )
        );
    }

    /**
     * Return an array containing the type name and id.
     *
     * @param  string  $argumentValue
     * @return string[]
     */
    public function transform($argumentValue): array
    {
        return $this->globalId->decode($argumentValue);
    }
}

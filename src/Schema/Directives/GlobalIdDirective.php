<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;

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
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $type = $fieldValue->getParentName();
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
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
     * Decodes a global id given as an argument.
     *
     * @param  string  $argumentValue
     * @return string|string[]
     */
    public function transform($argumentValue)
    {
        if ($decode = $this->directiveArgValue('decode')) {
            switch ($decode) {
                case 'TYPE':
                    return $this->globalId->decodeType($argumentValue);
                case 'ID':
                    return $this->globalId->decodeID($argumentValue);
                case 'ARRAY':
                    return $this->globalId->decode($argumentValue);
                default:
                    throw new DefinitionException(
                        "The only argument of the @globalId directive can only be ID or TYPE, got {$decode}"
                    );
            }
        }

        return $this->globalId->decode($argumentValue);
    }
}

<?php

namespace Nuwave\Lighthouse\GlobalId;

use Closure;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgSanitizerDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;

class GlobalIdDirective extends BaseDirective implements FieldMiddleware, ArgSanitizerDirective, ArgDirective
{
    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\GlobalId
     */
    protected $globalId;

    public function __construct(GlobalId $globalId)
    {
        $this->globalId = $globalId;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Converts between IDs/types and global IDs.
When used upon a field, it encodes,
when used upon an argument, it decodes.
"""
directive @globalId(
  """
  By default, an array of `[$type, $id]` is returned when decoding.
  You may limit this to returning just one of both.
  Allowed values: ARRAY, TYPE, ID
  """
  decode: String = ARRAY
) on FIELD_DEFINITION | INPUT_FIELD_DEFINITION | ARGUMENT_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $type = $fieldValue->getParentName();
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function () use ($type, $resolver): string {
                    $resolvedValue = $resolver(...func_get_args());

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
     * @return string|array<string>
     */
    public function sanitize($argumentValue)
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
                        "The decode argument of the @globalId directive can only be TYPE, ARRAY or ID, got {$decode}"
                    );
            }
        }

        return $this->globalId->decode($argumentValue);
    }
}

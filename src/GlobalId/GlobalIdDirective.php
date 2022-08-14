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

When used upon a field, it encodes;
when used upon an argument, it decodes.
"""
directive @globalId(
  """
  Decoding a global id produces a tuple of `$type` and `$id`.
  This setting controls which of those is passed along.
  """
  decode: GlobalIdDecode = ARRAY
) on FIELD_DEFINITION | INPUT_FIELD_DEFINITION | ARGUMENT_DEFINITION

"""
Options for the `decode` argument of `@globalId`.
"""
enum GlobalIdDecode {
    """
    Return an array of `[$type, $id]`.
    """
    ARRAY

    """
    Return just `$type`.
    """
    TYPE

    """
    Return just `$id`.
    """
    ID
}
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $type = $fieldValue->getParentName();

        $fieldValue->resultHandler(function ($result) use ($type) {
            if (null === $result) {
                return null;
            }

            return $this->globalId->encode($type, $result);
        });

        return $next($fieldValue);
    }

    /**
     * Decodes a global id given as an argument.
     *
     * @param  string|null  $argumentValue
     *
     * @return string|array{0: string, 1: string}|null
     */
    public function sanitize($argumentValue)
    {
        if (null === $argumentValue) {
            return null;
        }

        $decode = $this->directiveArgValue('decode');
        if (null !== $decode) {
            switch ($decode) {
                case 'TYPE':
                    return $this->globalId->decodeType($argumentValue);
                case 'ID':
                    return $this->globalId->decodeID($argumentValue);
                case 'ARRAY':
                    return $this->globalId->decode($argumentValue);
                default:
                    throw new DefinitionException("The decode argument of the @{$this->name()} directive can only be TYPE, ARRAY or ID, got {$decode}.");
            }
        }

        return $this->globalId->decode($argumentValue);
    }
}

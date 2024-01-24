<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\GlobalId;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgSanitizerDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class GlobalIdDirective extends BaseDirective implements FieldMiddleware, ArgSanitizerDirective, ArgDirective
{
    public const NAME = 'globalId';

    public function __construct(
        protected GlobalId $globalId,
    ) {}

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

    public function handleField(FieldValue $fieldValue): void
    {
        $type = $fieldValue->getParentName();

        $fieldValue->resultHandler(
            fn ($result): ?string => $result === null
                ? null
                : $this->globalId->encode($type, $result),
        );
    }

    /**
     * Decodes a global id given as an argument.
     *
     * @return string|array{0: string, 1: string}|null
     */
    public function sanitize(mixed $argumentValue): string|array|null
    {
        if ($argumentValue === null) {
            return null;
        }

        $decode = $this->directiveArgValue('decode');
        if ($decode !== null) {
            return match ($decode) {
                'TYPE' => $this->globalId->decodeType($argumentValue),
                'ID' => $this->globalId->decodeID($argumentValue),
                'ARRAY' => $this->globalId->decode($argumentValue),
                default => throw new DefinitionException("The decode argument of the @{$this->name()} directive can only be TYPE, ARRAY or ID, got {$decode}."),
            };
        }

        return $this->globalId->decode($argumentValue);
    }
}

<?php declare(strict_types=1);

namespace Tests\Utils\LaravelEnums;

use BenSampo\Enum\Enum;

/**
 * @extends \BenSampo\Enum\Enum<string>
 */
final class PartiallyDeprecated extends Enum
{
    public const NOT = 'NOT';

    /** @deprecated */
    public const DEPRECATED = 'DEPRECATED';

    /** @deprecated some reason */
    public const DEPRECATED_WITH_REASON = 'DEPRECATED_WITH_REASON';
}

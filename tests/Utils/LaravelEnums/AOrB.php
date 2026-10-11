<?php declare(strict_types=1);

namespace Tests\Utils\LaravelEnums;

use BenSampo\Enum\Enum;

/**
 * @extends \BenSampo\Enum\Enum<string>
 *
 * @method static static A()
 * @method static static B()
 */
final class AOrB extends Enum
{
    public const A = 'A';

    public const B = 'B';
}

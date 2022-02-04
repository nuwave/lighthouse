<?php

namespace Tests\Utils\LaravelEnums;

use BenSampo\Enum\Enum;

/**
 * @method static static A()
 * @method static static B()
 */
final class AOrB extends Enum
{
    public const A = 'A';
    public const B = 'B';
}

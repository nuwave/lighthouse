<?php

namespace Tests\Utils\LaravelEnums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

final class LocalizedUserType extends Enum implements LocalizedEnum
{
    public const Administrator = 'ADMINISTRATOR';
    public const Moderator = 'MODERATOR';

    public static function getDescription($value): string
    {
        if ($value === self::Moderator) {
            return 'Localize Moderator';
        }

        return parent::getDescription($value);
    }
}

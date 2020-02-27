<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Utils;
use stdClass;

class Undefined
{
    public static function undefined()
    {
        static $undefined;

        return $undefined ?: $undefined = new stdClass();
    }

    public static function removeUndefined(ArgumentSet $withUndefined): ArgumentSet
    {
        $withoutUndefined = new ArgumentSet();
        $withoutUndefined->directives = $withUndefined->directives;

        foreach ($withUndefined->arguments as $name => $argument) {
            $value = $argument->value;

            if ($value === self::undefined()) {
                continue;
            }

            $argument->value = Utils::applyEach(
                function ($value) {
                    return $value instanceof ArgumentSet
                        ? static::removeUndefined($value)
                        : $value;
                },
                $value
            );

            $withoutUndefined->arguments[$name] = $argument;
        }

        return $withoutUndefined;
    }
}

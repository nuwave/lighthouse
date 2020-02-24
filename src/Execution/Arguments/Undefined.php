<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

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
            } elseif($value instanceof ArgumentSet) {
                $argument->value = self::removeUndefined($value);
            }

            $withoutUndefined->arguments[$name] = $argument;
        }

        return $withoutUndefined;
    }
}

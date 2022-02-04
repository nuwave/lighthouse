<?php

namespace Tests\Utils\Queries;

class FooBar
{
    public const INVOKE_RESULT = 'foobaz';
    public const CUSTOM_RESOLVE_RESULT = 'barbaz';

    /**
     * Return a value for the field.
     */
    public function __invoke(): string
    {
        return self::INVOKE_RESULT;
    }

    /**
     * Return a value for the field.
     */
    public function customResolve(): string
    {
        return self::CUSTOM_RESOLVE_RESULT;
    }
}

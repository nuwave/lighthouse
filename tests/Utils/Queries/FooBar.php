<?php

namespace Tests\Utils\Queries;

class FooBar
{
    public const INVOKE_RESULT = 'foobaz';
    public const RESOLVE_RESULT = 'foobar';
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
    public function resolve(): string
    {
        return self::RESOLVE_RESULT;
    }

    /**
     * Return a value for the field.
     */
    public function customResolve(): string
    {
        return self::CUSTOM_RESOLVE_RESULT;
    }
}

<?php

namespace Tests\Utils\Queries;

class FooBar
{
    const RESOLVE_RESULT = 'foobar';
    const CUSTOM_RESOLVE_RESULT = 'barbaz';

    /**
     * Return a value for the field.
     *
     * @return string
     */
    public function resolve(): string
    {
        return self::RESOLVE_RESULT;
    }

    /**
     * Return a value for the field.
     *
     * @return string
     */
    public function customResolve(): string
    {
        return self::CUSTOM_RESOLVE_RESULT;
    }
}

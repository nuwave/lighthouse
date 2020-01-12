<?php

namespace Tests\Utils\Queries;

class Bar
{
    const RESULT = 'foobaz';

    public function __invoke(): string
    {
        return self::RESULT;
    }
}

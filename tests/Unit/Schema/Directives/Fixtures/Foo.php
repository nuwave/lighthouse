<?php

namespace Tests\Unit\Schema\Directives\Fixtures;

/**
 * TODO remove in favor of ->getMockBuilder(\stdClass::class)->addMethods(['__invoke'])
 * once we no longer support PHPUnit 7.
 */
/* not final because mocked */ class Foo
{
    public function bar(): void
    {
    }
}

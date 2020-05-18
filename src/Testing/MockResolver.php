<?php

namespace Nuwave\Lighthouse\Testing;

/**
 * TODO remove in favor of ->getMockBuilder(\stdClass::class)->addMethods(['__invoke'])
 * once we no longer support PHPUnit 7.
 */
class MockResolver
{
    /**
     * @return mixed|void Anything whatsoever.
     */
    public function __invoke()
    {
    }
}

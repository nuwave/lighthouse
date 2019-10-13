<?php

namespace Tests\Unit\Events;

use Tests\TestCase;
use Tests\Utils\Queries\Foo;
use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Events\ManipulateResult;

class ManipulateResultTest extends TestCase
{
    public function testCanManipulateTheResult(): void
    {
        Event::listen(
            ManipulateResult::class,
            function (ManipulateResult $manipulateResult): void {
                $manipulateResult->result->data = [
                    'foo' => Foo::THE_ANSWER + 1,
                ];
            }
        );

        $this->graphQL('
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER + 1,
            ],
        ]);
    }
}

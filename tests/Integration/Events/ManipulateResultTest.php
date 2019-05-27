<?php

namespace Tests\Integration\Events;

use Tests\TestCase;
use Tests\Utils\Queries\Foo;
use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Events\ManipulateResult;

class ManipulateResultTest extends TestCase
{
    /**
     * @test
     */
    public function itCanManipulateTheResult(): void
    {
        $this->schema = $this->placeholderQuery();

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

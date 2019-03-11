<?php

namespace Tests\Integration\Events;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Execution\GraphQLRequest;
use Tests\TestCase;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use Tests\Utils\Queries\Foo;

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
                    'foo' => Foo::THE_ANSWER + 1
                ];
            }
        );

        $this->query('
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER + 1
            ]
        ]);
    }
}

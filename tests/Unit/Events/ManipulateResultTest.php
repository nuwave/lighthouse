<?php declare(strict_types=1);

namespace Tests\Unit\Events;

use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

final class ManipulateResultTest extends TestCase
{
    public function testManipulateTheResult(): void
    {
        Event::listen(
            ManipulateResult::class,
            static function (ManipulateResult $manipulateResult): void {
                $manipulateResult->result->data = [
                    'foo' => Foo::THE_ANSWER + 1,
                ];
            },
        );

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER + 1,
            ],
        ]);
    }
}

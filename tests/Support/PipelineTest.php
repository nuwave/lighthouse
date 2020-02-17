<?php

namespace Tests\Unit\Support;

use Illuminate\Contracts\Support\Responsable;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    public function testDoesNotCallToResponse(): void
    {
        $this->mockResolver(new class implements Responsable {
            public $bar = 42;

            public function toResponse($request)
            {
                throw new \Exception('Must not be called when returning this from a resolver.');
            }
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Foo @mock
        }

        type Foo {
            bar: Int
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                bar
            }
        }
        ')->assertExactJson([
            'data' => [
                'foo' => [
                    'bar' => 42,
                ],
            ],
        ]);
    }
}

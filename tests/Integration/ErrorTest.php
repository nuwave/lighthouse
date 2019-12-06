<?php

namespace Tests\Integration;

use Tests\TestCase;

class ErrorTest extends TestCase
{
    public function testMissingQuery(): void
    {
        $this->postGraphQL([])
            ->assertExactJson([
                'errors' => [
                    'foo',
                ],
            ]);
    }

    public function testRejectsInvalidQuery(): void
    {
        $result = $this->graphQL('
        {
            nonExistingField
        }
        ');

        // TODO remove as we stop supporting Laravel 5.5/PHPUnit 6
        $assertContains = method_exists($this, 'assertStringContainsString')
            ? 'assertStringContainsString'
            : 'assertContains';

        $this->{$assertContains}(
            'nonExistingField',
            $result->jsonGet('errors.0.message')
        );
    }

    public function testIgnoresInvalidJSONVariables(): void
    {
        $result = $this->postGraphQL([
            'query' => '{}',
            'variables' => '{}',
        ]);

        $result->assertStatus(200);
    }

    public function testRejectsEmptyRequest(): void
    {
        $this->postGraphQL([])
            ->assertStatus(200)
            ->assertJson([
                [
                    'errors' => [
                        [
                            'message' => 'Syntax Error: Unexpected <EOF>',
                            'extensions' => [
                                'category' => 'graphql',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function testRejectsEmptyQuery(): void
    {
        $this->graphQL('')
            ->assertStatus(200)
            ->assertJson([
                'errors' => [
                    [
                        'message' => 'Syntax Error: Unexpected <EOF>',
                        'extensions' => [
                            'category' => 'graphql',
                        ],
                    ],
                ],
            ]);
    }
}

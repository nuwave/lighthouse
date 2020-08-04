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

        $this->assertStringContainsString(
            'nonExistingField',
            $result->json('errors.0.message')
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

<?php

namespace Tests\Integration;

use Tests\DBTestCase;

class DefaultSchemaTest extends DBTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->schema = \Safe\file_get_contents(__DIR__ . '/../../src/default-schema.graphql');
    }

    public function testEmptyUsers(): void
    {
        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users {
                    id
                }
            }
            ')
            ->assertExactJson([
                'data' => [
                    'users' => [],
                ],
            ]);
    }
}

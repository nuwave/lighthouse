<?php

namespace Tests\Unit\Testing;

use Tests\TestCase;

class MakesGraphQLRequestsTest extends TestCase
{
    public function testGraphQLEndpointUrl(): void
    {
        $this->assertEquals($this->graphQLEndpointUrl(), 'http://localhost/test-api/graphql');
    }
}

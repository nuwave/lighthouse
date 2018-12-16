<?php

namespace Tests\Unit\Execution;

use Tests\TestCase;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Execution\GraphQLRequest;

class GraphQLRequestTest extends TestCase
{
    /**
     * @test
     */
    public function itHandlesRegularQuery()
    {
        $query = '{ foo }';
        $variables = ['foo' => 123];
        $operationName = 'bar';
        $request = $this->makeGraphQLRequest([
            'query' => $query,
            'variables' => $variables,
            'operationName' => $operationName,
        ]);

        $this->assertFalse($request->isBatched());
        $this->assertNull($request->batchIndex());

        $this->assertSame($query, $request->query());
        $this->assertSame($variables, $request->variables());
        $this->assertSame($operationName, $request->operationName());
    }

    /**
     * @test
     */
    public function itAlwaysReturnsVariablesAsAnArray()
    {
        $request = $this->makeGraphQLRequest([
            'query' => '{ foo }',
            'variables' => ['foo' => 123],
        ]);
        $this->assertSame(['foo' => 123], $request->variables());

        $request = $this->makeGraphQLRequest([
            'query' => '{ foo }',
            'variables' => '{ "foo": 123 }',
        ]);
        $this->assertSame(['foo' => 123], $request->variables());

        $request = $this->makeGraphQLRequest([
            'query' => '{ foo }',
            'variables' => '',
        ]);
        $this->assertSame([], $request->variables());

        $request = $this->makeGraphQLRequest([
            'query' => '{ foo }',
        ]);
        $this->assertSame([], $request->variables());
    }

    /**
     * @test
     */
    public function itCanReturnNullForOperationName()
    {
        $request = $this->makeGraphQLRequest([
            'query' => '{ foo }',
        ]);
        $this->assertNull($request->operationName());
    }

    /**
     * @test
     */
    public function itHandlesBatchedQuery()
    {
        $firstQuery = '{ foo }';
        $secondQuery = '{ bar }';
        $request = $this->makeGraphQLRequest([
            [
                'query' => $firstQuery,
            ],
            [
                'query' => $secondQuery,
            ],
        ]);

        $this->assertTrue($request->isBatched());
        $this->assertSame(0, $request->batchIndex());
        $this->assertSame($firstQuery, $request->query());

        $this->assertTrue($request->advanceBatchIndex());
        $this->assertSame(1, $request->batchIndex());
        $this->assertSame($secondQuery, $request->query());

        $this->assertFalse($request->advanceBatchIndex());
    }

    /**
     * @param array $params
     *
     * @return GraphQLRequest
     */
    protected function makeGraphQLRequest(array $params): GraphQLRequest
    {
        return new GraphQLRequest(
            new Request($params)
        );
    }
}

<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Response;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Routing\Controller;
use Nuwave\Lighthouse\Execution\GraphQLRequest;
use Nuwave\Lighthouse\Exceptions\ParseException;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLResponse;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class GraphQLController extends Controller
{
    /** @var GraphQL */
    protected $graphQL;

    /** @var ExtensionRegistry */
    protected $extensionRegistry;

    /** @var GraphQLResponse */
    protected $graphQLResponse;

    /**
     * @param GraphQL           $graphQL
     * @param ExtensionRegistry $extensionRegistry
     * @param GraphQLResponse   $graphQLResponse
     */
    public function __construct(
        GraphQL $graphQL,
        ExtensionRegistry $extensionRegistry,
        GraphQLResponse $graphQLResponse
    ) {
        $this->graphQL = $graphQL;
        $this->extensionRegistry = $extensionRegistry;
        $this->graphQLResponse = $graphQLResponse;
    }

    /**
     * Execute GraphQL query.
     *
     * @param GraphQLRequest $request
     *
     * @throws DirectiveException
     * @throws ParseException
     *
     * @return Response
     */
    public function query(GraphQLRequest $request)
    {
        $response = $request->isBatched()
            ? $this->executeBatched($request)
            : $this->execute($request);

        return $this->graphQLResponse->create(
            $this->extensionRegistry->willSendResponse($response)
        );
    }

    /**
     * @param GraphQLRequest $request
     *
     * @throws DirectiveException
     * @throws ParseException
     *
     * @return array
     */
    protected function execute(GraphQLRequest $request): array
    {
        $this->extensionRegistry->start($request);

        return $this->graphQL->executeRequest($request);
    }

    /**
     * @param GraphQLRequest $request
     *
     * @throws DirectiveException
     * @throws ParseException
     *
     * @return array
     */
    protected function executeBatched(GraphQLRequest $request)
    {
        $results = [];

        do {
            $results[] = $this->execute($request);
        } while ($request->advanceBatchIndex());

        return $results;
    }
}

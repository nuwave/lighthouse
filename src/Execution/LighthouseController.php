<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Server\OperationParams;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Http\Request;
use Laragraph\LaravelGraphQLUtils\RequestParser;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Nuwave\Lighthouse\Support\Utils;

class LighthouseController
{
    /**
     * @var \Nuwave\Lighthouse\GraphQL
     */
    protected $graphQL;

    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventsDispatcher;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\CreatesResponse
     */
    protected $createsResponse;

    /**
     * @var \Illuminate\Container\Container
     */
    protected $container;

    /**
     * @var \Laragraph\LaravelGraphQLUtils\RequestParser
     */
    protected $requestParser;

    /**
     * Inject middleware into request.
     */
    public function __construct(
        GraphQL $graphQL,
        EventsDispatcher $eventsDispatcher,
        CreatesResponse $createsResponse,
        Container $container,
        RequestParser $requestParser
    ) {
        $this->graphQL = $graphQL;
        $this->eventsDispatcher = $eventsDispatcher;
        $this->createsResponse = $createsResponse;
        $this->container = $container;
        $this->requestParser = $requestParser;
    }

    public function __invoke(Request $request)
    {
        $operationParams = $this->requestParser->parseRequest($request);

        $result = Utils::applyEach(
            function (OperationParams $operationParams) {
                return $this->graphQL->executeOperation($operationParams);
            },
            $operationParams
        );

        $response = $this->createsResponse->createResponse($result);

        // When handling multiple requests during the application lifetime,
        // for example in tests, we need a new GraphQLRequest instance
        // for each HTTP request, so we forget the singleton here.
        $this->container->forgetInstance(GraphQLRequest::class);

        return $response;

        $operationParams = $this->parseRequest($request);
    }
}

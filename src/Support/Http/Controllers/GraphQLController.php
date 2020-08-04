<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laragraph\LaravelGraphQLUtils\RequestParser;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Nuwave\Lighthouse\Support\Utils;
use Symfony\Component\HttpFoundation\Response;

class GraphQLController extends Controller
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
     * @var \Laragraph\LaravelGraphQLUtils\RequestParser
     */
    protected $requestParser;

    /**
     * @var \GraphQL\Server\Helper
     */
    protected $graphQLHelper;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\CreatesResponse
     */
    protected $createsResponse;

    public function __construct(
        GraphQL $graphQL,
        EventsDispatcher $eventsDispatcher,
        RequestParser $requestParser,
        Helper $graphQLHelper,
        CreatesResponse $createsResponse
    ) {
        $this->graphQL = $graphQL;
        $this->eventsDispatcher = $eventsDispatcher;
        $this->requestParser = $requestParser;
        $this->graphQLHelper = $graphQLHelper;
        $this->createsResponse = $createsResponse;
    }

    /**
     * Execute GraphQL query.
     */
    public function __invoke(Request $request): Response
    {
        $this->eventsDispatcher->dispatch(
            new StartRequest($request)
        );

        $operationParams = $this->requestParser->parseRequest($request);

        $result = Utils::applyEach(
            function (OperationParams $operationParams) {
                // TODO handle those validation errors
                $errors = $this->graphQLHelper->validateOperationParams($operationParams);

                return $this->graphQL->executeOperation($operationParams);
            },
            $operationParams
        );

        return $this->createsResponse->createResponse($result);
    }
}

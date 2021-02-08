<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use GraphQL\Server\Helper;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Http\Request;
use Laragraph\Utils\RequestParser;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Symfony\Component\HttpFoundation\Response;

class GraphQLController
{
    public function __invoke(
        Request $request,
        GraphQL $graphQL,
        EventsDispatcher $eventsDispatcher,
        RequestParser $requestParser,
        Helper $graphQLHelper,
        CreatesResponse $createsResponse
    ): Response {
        $eventsDispatcher->dispatch(
            new StartRequest($request)
        );

        $result = $graphQL->executeRequest($request, $requestParser, $graphQLHelper);

        return $createsResponse->createResponse($result);
    }
}

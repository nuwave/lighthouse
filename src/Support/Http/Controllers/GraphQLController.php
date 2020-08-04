<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Http\Request;
use Laragraph\LaravelGraphQLUtils\RequestParser;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Nuwave\Lighthouse\Support\Utils;
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

        $operationParams = $requestParser->parseRequest($request);

        $result = Utils::applyEach(
            static function (OperationParams $operationParams) use ($graphQLHelper, $graphQL) {
                // TODO handle those validation errors
                $errors = $graphQLHelper->validateOperationParams($operationParams);

                return $graphQL->executeOperation($operationParams);
            },
            $operationParams
        );

        return $createsResponse->createResponse($result);
    }
}

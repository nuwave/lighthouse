<?php

namespace Tests\Integration;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\TestCase;

class GraphQLControllerTest extends TestCase
{
    public function testWrongJson(): void
    {
        $content = '{wrong json}';

        $headers = [
            'CONTENT_LENGTH' => mb_strlen($content, '8bit'),
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ];

        $this->expectException(BadRequestHttpException::class);
        $this->call(
            'POST',
            $this->graphQLEndpointUrl(),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $content
        );
    }
}

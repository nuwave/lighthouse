<?php

namespace Tests\Unit\Schema\Extensions;

use Nuwave\Lighthouse\Schema\Extensions\DeferExtension;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
use Nuwave\Lighthouse\Support\Http\Responses\MemoryStream;

trait RequestsStreamedResponses
{
    /**
     * @var \Nuwave\Lighthouse\Support\Http\Responses\MemoryStream
     */
    protected $stream;

    protected function setUpInMemoryStream($app)
    {
        $this->stream = new MemoryStream();

        $app->singleton(CanStreamResponse::class, function (): MemoryStream {
            return $this->stream;
        });

        $app['config']->set('lighthouse.extensions', [DeferExtension::class]);
    }

    /**
     * Send the query and capture all chunks of the streamed response.
     *
     * @param  string  $query
     * @return array
     */
    protected function getStreamedChunks(string $query): array
    {
        $this->query($query)
            ->baseResponse
            ->send();

        return $this->stream->chunks;
    }
}

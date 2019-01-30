<?php

namespace Tests\Integration\Defer;

use Nuwave\Lighthouse\Defer\Defer;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
use Nuwave\Lighthouse\Support\Http\Responses\MemoryStream;

trait SetUpDefer
{
    /**
     * @var \Nuwave\Lighthouse\Support\Http\Responses\MemoryStream
     */
    protected $stream;

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function setUpDefer($app)
    {
        $this->stream = new MemoryStream();

        $app->singleton(CanStreamResponse::class, function (): MemoryStream {
            return $this->stream;
        });

        $app['config']->set('lighthouse.extensions', [Defer::class]);
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

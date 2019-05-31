<?php

namespace Tests\Integration\Defer;

use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
use Nuwave\Lighthouse\Support\Http\Responses\MemoryStream;

trait SetUpDefer
{
    /**
     * @var \Nuwave\Lighthouse\Support\Http\Responses\MemoryStream
     */
    protected $stream;

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function setUpDefer($app): void
    {
        $this->stream = new MemoryStream;

        $app->singleton(CanStreamResponse::class, function (): MemoryStream {
            return $this->stream;
        });
    }

    /**
     * Send the query and capture all chunks of the streamed response.
     *
     * @param  string  $query
     * @return array
     */
    protected function getStreamedChunks(string $query): array
    {
        $this->graphQL($query)
            ->baseResponse
            ->send();

        return $this->stream->chunks;
    }
}

<?php

namespace Nuwave\Lighthouse\Support\Http\Responses;

class Stream implements CanSendResponse
{
    /** @var bool */
    protected $sent = false;

    /**
     * Send response.
     *
     * @param array $data
     * @param array $paths
     */
    public function send(array $data, array $paths = [])
    {
        $stream = $this->chunk($data);

        if (! $this->sent) {
            $stream = $this->boundary().$stream;
        }

        $this->stream($stream);

        $this->sent = true;
    }

    protected function boundary(): string
    {
        return "---\n";
    }

    protected function chunk(array $data): string
    {
        $json = json_encode($data);

        return implode("\n", [
            'Content-Type: application/json',
            'Content-Length: '.strlen($json),
            $json,
            $this->boundary(),
        ]);
    }

    protected function stream(string $response)
    {
        echo $response;
        ob_flush();
        flush();
    }
}

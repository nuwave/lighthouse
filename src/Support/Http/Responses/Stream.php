<?php

namespace Nuwave\Lighthouse\Support\Http\Responses;

class Stream implements CanSendResponse
{
    const EOL = "\r\n";

    protected $appendLength = 2;

    /**
     * Send response.
     *
     * @param array $data
     * @param array $paths
     * @param bool  $final
     */
    public function send(array $data, array $paths = [], bool $final)
    {
        if ($final) {
            $this->appendLength += 2;
        }

        if (! empty($paths)) {
            $paths = collect($paths);
            $lastKey = $paths->count() - 1;
            $paths->map(function ($path, $i) use ($data, $final, $lastKey) {
                $terminating = $final && ($i == $lastKey);

                return $this->chunk([
                    'path' => collect(explode('.', $path))->map(function ($partial) {
                        return is_numeric($partial) ? intval($partial) : $partial;
                    })->toArray(),
                    'data' => array_get($data, "data.{$path}"),
                ], $terminating);
            })->each(function ($chunk) {
                $this->emit($chunk);
            });
        } else {
            $this->emit($this->chunk($data, $final));
        }

        if ($final) {
            $this->emit($this->terminatingBoundary());
        }
    }

    protected function boundary(): string
    {
        return self::EOL.'---'.self::EOL;
    }

    protected function terminatingBoundary(): string
    {
        return self::EOL.'-----'.self::EOL;
    }

    protected function chunk(array $data, $terminating = false): string
    {
        $json = json_encode($data, 0);
        $length = $terminating ? strlen($json) : strlen($json.self::EOL);

        $chunk = implode(self::EOL, [
            'Content-Type: application/json',
            'Content-Length: '.$length,
            null,
            $json,
            null,
        ]);

        return $this->boundary().$chunk;
    }

    protected function emit(string $chunk)
    {
        echo $chunk;
        ob_flush();
        flush();
    }
}

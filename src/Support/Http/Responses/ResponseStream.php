<?php

namespace Nuwave\Lighthouse\Support\Http\Responses;

use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;

class ResponseStream implements CanStreamResponse
{
    /** @var string */
    const EOL = "\r\n";

    /**
     * Stream graphql response.
     *
     * @param array $data
     * @param array $paths
     * @param bool  $final
     *
     * @return mixed
     */
    public function stream(array $data, array $paths = [], bool $final)
    {
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

    /**
     * @return string
     */
    protected function boundary(): string
    {
        return self::EOL.'---'.self::EOL;
    }

    /**
     * @return string
     */
    protected function terminatingBoundary(): string
    {
        return self::EOL.'-----'.self::EOL;
    }

    /**
     * Format chunked data.
     *
     * @param array $data
     * @param bool  $terminating
     *
     * @return string
     */
    protected function chunk(array $data, bool $terminating): string
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

    /**
     * Stream chunked data to client.
     *
     * @param string $chunk
     */
    protected function emit(string $chunk)
    {
        echo $chunk;

        $this->flush(\Closure::fromCallable('ob_flush'));
        $this->flush(\Closure::fromCallable('flush'));
    }

    /**
     * Flush buffer cache.
     * Note: We can run into exceptions when flushing the buffer,
     * these should be safe to ignore.
     *
     * @todo Investigate exceptions that occur on Apache
     */
    protected function flush(\Closure $flush)
    {
        try {
            $flush();
        } catch (\Exception $e) {
            // buffer error, do nothing...
        } catch (\Error $e) {
            // buffer error, do nothing...
        }
    }
}

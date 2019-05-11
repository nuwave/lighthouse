<?php

namespace Nuwave\Lighthouse\Support\Http\Responses;

use Closure;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;

class ResponseStream extends Stream implements CanStreamResponse
{
    /**
     * @var string
     */
    const EOL = "\r\n";

    /**
     * Stream graphql response.
     *
     * @param  array  $data
     * @param  array  $paths
     * @param  bool  $final
     * @return void
     */
    public function stream(array $data, array $paths, bool $final): void
    {
        if (! empty($paths)) {
            $paths = new Collection($paths);
            $lastKey = $paths->count() - 1;

            $paths
                ->map(function (string $path, int $i) use ($data, $final, $lastKey): string {
                    $terminating = $final && ($i === $lastKey);
                    $chunk['data'] = Arr::get($data, "data.{$path}");
                    $chunk['path'] = (new Collection(explode('.', $path)))
                        ->map(function ($partial) {
                            return is_numeric($partial)
                                ? (int) $partial
                                : $partial;
                        })
                        ->all();

                    $errors = $this->chunkError($path, $data);
                    if (! empty($errors)) {
                        $chunk['errors'] = $errors;
                    }

                    return $this->chunk($chunk, $terminating);
                })
                ->each(function (string $chunk) {
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
     * @param  array  $data
     * @param  bool  $terminating
     * @return string
     */
    protected function chunk(array $data, bool $terminating): string
    {
        $json = json_encode($data, 0);
        $length = $terminating
            ? strlen($json)
            : strlen($json.self::EOL);

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
     * @param  string  $chunk
     * @return void
     */
    protected function emit(string $chunk): void
    {
        echo $chunk;

        $this->flush(Closure::fromCallable('ob_flush'));
        $this->flush(Closure::fromCallable('flush'));
    }

    /**
     * Flush buffer cache.
     * Note: We can run into exceptions when flushing the buffer,
     * these should be safe to ignore.
     * @todo Investigate exceptions that occur on Apache
     *
     * @param  \Closure  $flush
     * @return void
     */
    protected function flush(Closure $flush): void
    {
        try {
            $flush();
        } catch (Exception $e) {
            // buffer error, do nothing...
        }
    }
}

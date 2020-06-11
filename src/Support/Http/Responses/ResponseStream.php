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
    public const EOL = "\r\n";

    public function stream(array $data, array $paths, bool $final): void
    {
        if (! empty($paths)) {
            $chunk = [];
            $lastKey = count($paths) - 1;

            foreach ($paths as $i => $path) {
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

                $terminating = $final && $i === $lastKey;

                $this->emit(
                    $this->chunk($chunk, $terminating)
                );
            }
        } else {
            $this->emit(
                $this->chunk($data, $final)
            );
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

    /**
     * Format chunked data.
     *
     * @param  array<mixed>  $data
     */
    protected function chunk(array $data, bool $terminating): string
    {
        /** @var string $json */
        $json = json_encode($data);
        // TODO use \Safe\json_encode
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Tried to encode invalid JSON while sending response stream: '.json_last_error_msg());
        }

        $length = $terminating
            ? strlen($json)
            : strlen($json.self::EOL);

        $chunk = implode(self::EOL, [
            'Content-Type: application/json',
            'Content-Length: '.$length,
            '',
            $json,
            '',
        ]);

        return $this->boundary().$chunk;
    }

    /**
     * Stream chunked data to client.
     */
    protected function emit(string $chunk): void
    {
        echo $chunk;

        $this->flush(Closure::fromCallable('ob_flush'));
        $this->flush(Closure::fromCallable('flush'));
    }

    /**
     * Flush buffer cache.
     *
     * Note: We can run into exceptions when flushing the buffer, these should be safe to ignore.
     * TODO Investigate exceptions that occur on Apache
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

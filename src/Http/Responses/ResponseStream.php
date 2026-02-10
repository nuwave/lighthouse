<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Http\Responses;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;

class ResponseStream extends Stream implements CanStreamResponse
{
    protected const EOL = "\r\n";

    protected const BOUNDARY = self::EOL . '---' . self::EOL;

    protected const TERMINATING_BOUNDARY = self::EOL . '-----' . self::EOL;

    public function stream(array $data, array $paths, bool $isFinalChunk): void
    {
        if ($paths === []) {
            $this->emit(
                $this->chunk($data, $isFinalChunk),
            );
        } else {
            $chunk = [];
            $lastKey = count($paths) - 1;

            foreach ($paths as $i => $path) {
                $chunk['data'] = Arr::get($data, "data.{$path}");
                $chunk['path'] = (new Collection(explode('.', $path)))
                    ->map(static fn ($partial): int|string => is_numeric($partial)
                        ? (int) $partial
                        : $partial)
                    ->all();

                $errors = $this->chunkError($path, $data);
                if ($errors !== null && $errors !== []) {
                    $chunk['errors'] = $errors;
                }

                $terminating = $isFinalChunk && $i === $lastKey;

                $this->emit(
                    $this->chunk($chunk, $terminating),
                );
            }
        }

        if ($isFinalChunk) {
            $this->emit(self::TERMINATING_BOUNDARY);
        }
    }

    /**
     * Format chunked data.
     *
     * @param  array<mixed>  $data
     */
    protected function chunk(array $data, bool $terminating): string
    {
        $json = \Safe\json_encode($data);

        $length = $terminating
            ? strlen($json)
            : strlen($json . self::EOL);

        $chunk = implode(self::EOL, [
            'Content-Type: application/json',
            "Content-Length: {$length}",
            '',
            $json,
            '',
        ]);

        return self::BOUNDARY . $chunk;
    }

    /** Stream chunked data to client. */
    protected function emit(string $chunk): void
    {
        echo $chunk;

        $this->flush(\Closure::fromCallable('ob_flush'));
        $this->flush(\Closure::fromCallable('flush'));
    }

    /**
     * Flush buffer cache.
     *
     * @param  callable(): mixed  $flush
     *
     * Note: We can run into exceptions when flushing the buffer, these should be safe to ignore
     */
    protected function flush(callable $flush): void
    {
        try {
            $flush();
        } catch (\Exception) {
            // buffer error, do nothing...
        }
    }
}

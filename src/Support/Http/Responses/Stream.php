<?php

namespace Nuwave\Lighthouse\Support\Http\Responses;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

abstract class Stream
{
    /**
     * Get error from chunk if it exists.
     *
     * @param  string  $path
     * @param  array  $data
     * @return array|null
     */
    protected function chunkError(string $path, array $data): ?array
    {
        if (! isset($data['errors'])) {
            return null;
        }

        return (new Collection($data['errors']))
            ->filter(function (array $error) use ($path): bool {
                return Str::startsWith(implode('.', $error['path']), $path);
            })
            ->values()
            ->all();
    }
}

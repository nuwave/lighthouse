<?php

namespace Nuwave\Lighthouse\Testing;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\ValidationException;

/**
 * Because we can not have non-mixin methods in mixin classes.
 *
 * @see \Nuwave\Lighthouse\Testing\TestResponseMixin
 */
class TestResponseUtils
{
    /**
     * @param  \Illuminate\Testing\TestResponse  $response
     */
    public static function extractValidationErrors($response): ?array
    {
        $errors = $response->json('errors') ?? [];

        return Arr::first(
            $errors,
            function (array $error): bool {
                return Arr::get($error, 'extensions.category') === ValidationException::CATEGORY;
            }
        );
    }
}

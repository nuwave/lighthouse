<?php

namespace Nuwave\Lighthouse\Testing;

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
     *
     * @return array<string, array<int, string>>|null
     */
    public static function extractValidationErrors($response): ?array
    {
        $errors = $response->json('errors') ?? [];

        // @phpstan-ignore-next-line PHPStan 0.11 fails with "Empty array passed to foreach" TODO remove once no longer supporting Laravel 6
        foreach ($errors as $error) {
            $validation = $error['extensions'][ValidationException::CATEGORY]
                ?? null;

            if (is_array($validation)) {
                return $validation;
            }
        }

        return null;
    }
}

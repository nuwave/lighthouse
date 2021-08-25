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

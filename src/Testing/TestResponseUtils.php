<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Testing;

/**
 * Because we can not have non-mixin methods in mixin classes.
 *
 * @see TestResponseMixin
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
            $validation = $error['extensions']['validation']
                ?? null;

            if (is_array($validation)) {
                return $validation;
            }
        }

        return null;
    }
}

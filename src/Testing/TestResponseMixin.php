<?php

namespace Nuwave\Lighthouse\Testing;

use Closure;
use GraphQL\Error\ClientAware;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * TODO add stronger type hints once support for Illuminate\Foundation\Testing\TestResponse is no longer needed.
 *
 * @mixin \Illuminate\Testing\TestResponse
 */
class TestResponseMixin
{
    public function assertGraphQLValidationError(): Closure
    {
        return function (string $key, ?string $message) {
            $validation = TestResponseUtils::extractValidationErrors($this);
            Assert::assertNotNull($validation, 'Expected the query to return an error with extensions.validation.');

            Assert::assertArrayHasKey(
                $key,
                $validation,
                "Expected the query to return validation errors for field `{$key}`."
            );

            Assert::assertContains(
                $message,
                $validation[$key],
                "Expected the query to return validation error message `{$message}` for field `{$key}`."
            );

            return $this;
        };
    }

    public function assertGraphQLValidationKeys(): Closure
    {
        return function (array $keys) {
            $validation = TestResponseUtils::extractValidationErrors($this);
            Assert::assertNotNull($validation, 'Expected the query to return an error with extensions.validation.');

            Assert::assertSame(
                $keys,
                array_keys($validation),
                'Expected the query to return validation errors for specific fields.'
            );

            return $this;
        };
    }

    public function assertGraphQLValidationPasses(): Closure
    {
        return function () {
            $validation = TestResponseUtils::extractValidationErrors($this);
            Assert::assertNull($validation, 'Expected the query to have no validation errors.');

            return $this;
        };
    }

    public function assertGraphQLError(): Closure
    {
        return function (Throwable $error) {
            $message = $error->getMessage();

            return $error instanceof ClientAware && $error->isClientSafe()
                ? $this->assertGraphQLErrorMessage($message)
                : $this->assertGraphQLDebugMessage($message);
        };
    }

    public function assertGraphQLErrorMessage(): Closure
    {
        return function (string $message) {
            $messages = $this->json('errors.*.message');

            Assert::assertIsArray($messages, 'Expected the GraphQL response to contain errors, got none.');
            Assert::assertContains(
                $message,
                $messages,
                "Expected the GraphQL response to contain error message `{$message}`, got: " . \Safe\json_encode($messages)
            );

            return $this;
        };
    }

    public function assertGraphQLDebugMessage(): Closure
    {
        return function (string $message) {
            $messages = $this->json('errors.*.debugMessage');

            Assert::assertIsArray($messages, 'Expected the GraphQL response to contain errors, got none.');
            Assert::assertContains(
                $message,
                $messages,
                "Expected the GraphQL response to contain debug message `{$message}`, got: " . \Safe\json_encode($messages)
            );

            return $this;
        };
    }

    public function assertGraphQLErrorFree(): Closure
    {
        return function () {
            $errors = $this->json('errors');
            Assert::assertNull(
                $errors,
                'Expected the GraphQL response to contain no errors, got: ' . \Safe\json_encode($errors)
            );

            return $this;
        };
    }

    public function assertGraphQLErrorCategory(): Closure
    {
        return function (string $category) {
            $this->assertJson([
                'errors' => [
                    [
                        'extensions' => [
                            'category' => $category,
                        ],
                    ],
                ],
            ]);

            return $this;
        };
    }
}

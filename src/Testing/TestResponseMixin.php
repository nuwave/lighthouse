<?php

namespace Nuwave\Lighthouse\Testing;

use Closure;
use Nuwave\Lighthouse\Exceptions\ValidationException;
use PHPUnit\Framework\Assert;

/**
 * @mixin \Illuminate\Testing\TestResponse
 */
class TestResponseMixin
{
    public function assertGraphQLValidationError(): Closure
    {
        return function (string $key, ?string $message) {
            $this->assertJson([
                'errors' => [
                    [
                        'extensions' => [
                            'validation' => [
                                $key => [
                                    $message,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            return $this;
        };
    }

    public function assertGraphQLValidationKeys(): Closure
    {
        return function (array $keys) {
            $validation = TestResponseUtils::extractValidationErrors($this);

            Assert::assertNotNull($validation, 'Expected the query to return validation errors for specific fields.');
            /** @var array<string, mixed> $validation */
            Assert::assertArrayHasKey('extensions', $validation);
            $extensions = $validation['extensions'];

            Assert::assertNotNull($extensions, 'Expected the query to return validation errors for specific fields.');
            /** @var array<string, mixed> $extensions */
            Assert::assertSame(
                $keys,
                array_keys($extensions[ValidationException::CATEGORY]),
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

    public function assertGraphQLErrorMessage(): Closure
    {
        return function (string $message) {
            $messages = $this->json('errors.*.message');
            Assert::assertContains(
                $message,
                $messages,
                "Expected the GraphQL response to contain error message `{$message}`, got: ".\Safe\json_encode($messages)
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

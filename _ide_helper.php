<?php declare(strict_types=1);

namespace Illuminate\Testing;

use Mockery\MockInterface;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use PHPUnit\Framework\TestCase;

class TestResponse
{
    /**
     * Assert the response contains an error with a matching message.
     *
     * @param  \Throwable  $error  the expected error
     *
     * @return $this
     */
    public function assertGraphQLError(\Throwable $error): self
    {
        return $this;
    }

    /**
     * Assert the response contains an error with the given message.
     *
     * @param  string  $message  the expected error message
     *
     * @return $this
     */
    public function assertGraphQLErrorMessage(string $message): self
    {
        return $this;
    }

    /**
     * Assert the response contains an error with the given debug message.
     *
     * Requires the config `lighthouse.debug` to include the option \GraphQL\Error\DebugFlag::INCLUDE_DEBUG_MESSAGE.
     *
     * @param  string  $message  the expected debug message
     *
     * @return $this
     */
    public function assertGraphQLDebugMessage(string $message): self
    {
        return $this;
    }

    /**
     * Assert the response contains no errors.
     *
     * @return $this
     */
    public function assertGraphQLErrorFree(): self
    {
        return $this;
    }

    /**
     * Assert the returned result contains exactly the given validation keys.
     *
     * @param  array<string>  $keys  the validation keys the result should have
     *
     * @return $this
     */
    public function assertGraphQLValidationKeys(array $keys): self
    {
        return $this;
    }

    /**
     * Assert a given validation error is present in the response.
     *
     * @param  string  $key  the validation key that should be present
     * @param  string  $message  the expected validation message
     *
     * @return $this
     */
    public function assertGraphQLValidationError(string $key, string $message): self
    {
        return $this;
    }

    /**
     * Assert no validation errors are present in the response.
     *
     * @return $this
     */
    public function assertGraphQLValidationPasses(): self
    {
        return $this;
    }

    /**
     * Assert current user is authorized to join a subscription.
     *
     * @param  TestCase  $testClassInstance  usually $this when calling this from a test class
     *
     * @return $this
     */
    public function assertGraphQLSubscriptionAuthorized(TestCase $testClassInstance): self
    {
        return $this;
    }

    /**
     * Assert current user is NOT authorized to join a subscription.
     *
     * @param  TestCase  $testClassInstance  usually $this when calling this from a test class
     *
     * @return $this
     */
    public function assertGraphQLSubscriptionNotAuthorized(TestCase $testClassInstance): self
    {
        return $this;
    }

    /**
     * For cases where you need more control over your broadcast assertions.
     *
     * @see \Nuwave\Lighthouse\Testing\TestResponseMixin::assertGraphQLBroadcasted
     * @see \Nuwave\Lighthouse\Testing\TestResponseMixin::assertGraphQLNotBroadcasted
     *
     * @return \Mockery\MockInterface&\Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster
     */
    public function graphQLSubscriptionMock() // @phpstan-ignore-line invalid return type?
    {
        $mock = \Mockery::mock(Broadcaster::class);
        assert($mock instanceof Broadcaster && $mock instanceof MockInterface);

        return $mock;
    }

    /** Get the channel name from a subscription query. */
    public function graphQLSubscriptionChannelName(): string
    {
        return '';
    }

    /**
     * Assert the subscription received the given broadcasts.
     *
     * @param  array<int, array<string, mixed>>  $data  the broadcast pattern that you are expecting
     *
     * @return $this
     */
    public function assertGraphQLBroadcasted(array $data): self
    {
        return $this;
    }

    /**
     * Assert the subscription received no broadcast.
     *
     * @return $this
     */
    public function assertGraphQLNotBroadcasted(): self
    {
        return $this;
    }
}

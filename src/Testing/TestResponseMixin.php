<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Testing;

use GraphQL\Error\ClientAware;
use Illuminate\Container\Container;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * @mixin \Illuminate\Testing\TestResponse
 */
class TestResponseMixin
{
    public function assertGraphQLValidationError(): \Closure
    {
        return function (string $key, ?string $message): TestResponse {
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

    public function assertGraphQLValidationKeys(): \Closure
    {
        return function (array $keys): TestResponse {
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

    public function assertGraphQLValidationPasses(): \Closure
    {
        return function (): TestResponse {
            $validation = TestResponseUtils::extractValidationErrors($this);
            Assert::assertNull($validation, 'Expected the query to have no validation errors.');

            return $this;
        };
    }

    public function assertGraphQLError(): \Closure
    {
        return function (\Throwable $error): TestResponse {
            $message = $error->getMessage();

            return $error instanceof ClientAware && $error->isClientSafe()
                ? $this->assertGraphQLErrorMessage($message)
                : $this->assertGraphQLDebugMessage($message);
        };
    }

    public function assertGraphQLErrorMessage(): \Closure
    {
        return function (string $message): TestResponse {
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

    public function assertGraphQLDebugMessage(): \Closure
    {
        return function (string $message): TestResponse {
            $messages = $this->json('errors.*.extensions.debugMessage');

            Assert::assertIsArray($messages, 'Expected the GraphQL response to contain errors, got none.');
            Assert::assertContains(
                $message,
                $messages,
                "Expected the GraphQL response to contain debug message `{$message}`, got: " . \Safe\json_encode($messages)
            );

            return $this;
        };
    }

    public function assertGraphQLErrorFree(): \Closure
    {
        return function (): TestResponse {
            $errors = $this->json('errors');
            Assert::assertNull(
                $errors,
                'Expected the GraphQL response to contain no errors, got: ' . \Safe\json_encode($errors)
            );

            return $this;
        };
    }

    public function assertGraphQLSubscriptionAuthorized(): \Closure
    {
        return function (TestCase $testClassInstance): TestResponse {
            Assert::assertTrue(method_exists($testClassInstance, 'postJson'));

            $channel = $this->json('extensions.lighthouse_subscriptions.channel');

            $testClassInstance
                // @phpstan-ignore-next-line present in \Illuminate\Foundation\Testing\Concerns\MakesHttpRequests
                ->postJson('graphql/subscriptions/auth', [
                    'channel_name' => $channel,
                ])
                ->assertSuccessful()
                ->assertExactJson([
                    'message' => 'ok',
                ]);

            return $this;
        };
    }

    public function assertGraphQLSubscriptionNotAuthorized(): \Closure
    {
        return function (TestCase $testClassInstance): TestResponse {
            Assert::assertTrue(method_exists($testClassInstance, 'postJson'));

            $channel = $this->json('extensions.lighthouse_subscriptions.channel');

            $testClassInstance
                // @phpstan-ignore-next-line present in \Illuminate\Foundation\Testing\Concerns\MakesHttpRequests
                ->postJson('graphql/subscriptions/auth', [
                    'channel_name' => $channel,
                ])
                ->assertForbidden();

            return $this;
        };
    }

    public function graphQLSubscriptionMock(): \Closure
    {
        return function (): MockInterface {
            $broadcastManager = Container::getInstance()->make(BroadcastManager::class);
            assert($broadcastManager instanceof BroadcastManager);

            $mock = $broadcastManager->driver();
            assert($mock instanceof LogBroadcaster && $mock instanceof MockInterface);

            return $mock;
        };
    }

    public function graphQLSubscriptionChannelName(): \Closure
    {
        return function (): string {
            return $this->json('extensions.lighthouse_subscriptions.channel');
        };
    }

    public function assertGraphQLBroadcasted(): \Closure
    {
        $response = $this;

        return function ($data) use ($response): TestResponse {
            $i = 0;
            $channel = $this->json('extensions.lighthouse_subscriptions.channel');

            $mock = $response->graphQLSubscriptionMock()();
            assert($mock instanceof MockInterface);

            $concatinatedBroadcastedData = [];

            // @phpstan-ignore-next-line phpstan doesn't see that Parameter #2 can accept Closure even though it's type-hinted at LegacyMockInterface
            $mock->shouldHaveReceived('broadcast', function (Subscriber $subscriber, $broadcastedData) use ($channel, &$concatinatedBroadcastedData) {
                if ($channel === $subscriber->channel) {
                    $concatinatedBroadcastedData[] = array_values($broadcastedData['data'])[0];
                }

                return true;
            });

            Assert::assertEquals($concatinatedBroadcastedData, $data, 'Broadcasted data pattern does not match your expected definition');

            return $this;
        };
    }

    public function assertGraphQLNotBroadcasted(): \Closure
    {
        $response = $this;

        return function () use ($response): TestResponse {
            $channel = $this->json('extensions.lighthouse_subscriptions.channel');

            $mock = $response->graphQLSubscriptionMock()();
            assert($mock instanceof MockInterface);

            // @phpstan-ignore-next-line phpstan doesn't see that Parameter #2 can accept Closure even though it's type-hinted at LegacyMockInterface
            $mock->shouldNotHaveReceived('broadcast', fn (Subscriber $subscriber, $broadcastedData) => $channel !== $subscriber->channel);

            return $this;
        };
    }
}

<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Testing;

use GraphQL\Error\ClientAware;
use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
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
                "Expected the query to return validation errors for field `{$key}`.",
            );

            Assert::assertContains(
                $message,
                $validation[$key],
                "Expected the query to return validation error message `{$message}` for field `{$key}`.",
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
                'Expected the query to return validation errors for specific fields.',
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

            $messagesString = \Safe\json_encode($messages);
            Assert::assertContains($message, $messages, "Expected the GraphQL response to contain error message \"{$message}\", got: {$messagesString}.");

            return $this;
        };
    }

    public function assertGraphQLDebugMessage(): \Closure
    {
        return function (string $message): TestResponse {
            $messages = $this->json('errors.*.extensions.debugMessage');

            Assert::assertIsArray($messages, 'Expected the GraphQL response to contain errors, got none.');

            $messagesString = \Safe\json_encode($messages);
            Assert::assertContains($message, $messages, "Expected the GraphQL response to contain debug message \"{$message}\", got: {$messagesString}.");

            return $this;
        };
    }

    public function assertGraphQLErrorFree(): \Closure
    {
        return function (): TestResponse {
            $errors = $this->json('errors');
            $errorsString = \Safe\json_encode($errors);
            Assert::assertNull($errors, "Expected the GraphQL response to contain no errors, got: {$errorsString}.");

            return $this;
        };
    }

    public function assertGraphQLSubscriptionAuthorized(): \Closure
    {
        return function (TestCase $testClassInstance): TestResponse {
            Assert::assertTrue(method_exists($testClassInstance, 'postJson'), 'Expected the given $testClassInstance to use the trait Illuminate\\Foundation\\Testing\\Concerns\\MakesHttpRequests.');

            $testClassInstance
                // @phpstan-ignore-next-line present in \Illuminate\Foundation\Testing\Concerns\MakesHttpRequests
                ->postJson('graphql/subscriptions/auth', [
                    'channel_name' => $this->json('extensions.lighthouse_subscriptions.channel'),
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
            Assert::assertTrue(method_exists($testClassInstance, 'postJson'), 'Expected the given $testClassInstance to use the trait Illuminate\\Foundation\\Testing\\Concerns\\MakesHttpRequests.');

            $testClassInstance
                // @phpstan-ignore-next-line present in \Illuminate\Foundation\Testing\Concerns\MakesHttpRequests
                ->postJson('graphql/subscriptions/auth', [
                    'channel_name' => $this->json('extensions.lighthouse_subscriptions.channel'),
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
            assert($mock instanceof Broadcaster && $mock instanceof MockInterface);

            return $mock;
        };
    }

    public function graphQLSubscriptionChannelName(): \Closure
    {
        return fn (): string => $this->json('extensions.lighthouse_subscriptions.channel');
    }

    public function assertGraphQLBroadcasted(): \Closure
    {
        return function (array $data): TestResponse {
            $channel = $this->json('extensions.lighthouse_subscriptions.channel');

            $mock = $this->graphQLSubscriptionMock();
            assert($mock instanceof LegacyMockInterface); // @phpstan-ignore-line mixins are magical

            $broadcastedData = [];
            $mock->shouldHaveReceived('broadcast', static function (Subscriber $subscriber, array $data) use ($channel, &$broadcastedData): bool {
                Assert::assertArrayHasKey('data', $data);
                if ($channel === $subscriber->channel) {
                    $broadcastedData[] = Arr::first($data['data']);
                }

                return true;
            });

            Assert::assertEquals($broadcastedData, $data, 'Broadcasted data pattern does not match your expected definition.');

            return $this;
        };
    }

    public function assertGraphQLNotBroadcasted(): \Closure
    {
        return function (): TestResponse {
            $channel = $this->json('extensions.lighthouse_subscriptions.channel');

            $mock = $this->graphQLSubscriptionMock();
            assert($mock instanceof LegacyMockInterface); // @phpstan-ignore-line mixins are magical

            $mock->shouldNotHaveReceived('broadcast', static fn (Subscriber $subscriber, $data): bool => $channel !== $subscriber->channel);

            return $this;
        };
    }
}

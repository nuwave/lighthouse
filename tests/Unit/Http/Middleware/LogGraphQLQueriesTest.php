<?php declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Http\Middleware\LogGraphQLQueries;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

final class LogGraphQLQueriesTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&\Psr\Log\LoggerInterface */
    protected MockObject $logger;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $config = $app->make(ConfigRepository::class);
        $config->set('lighthouse.route.middleware', [
            LogGraphQLQueries::class,
        ]);

        $this->logger = $this->getMockForAbstractClass(LoggerInterface::class);
        $app->instance(LoggerInterface::class, $this->logger);
    }

    public function testLogsEveryQuery(): void
    {
        $query = /** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL;

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                LogGraphQLQueries::MESSAGE,
                [
                    'query' => $query,
                ],
            );

        $this->graphQL($query);
    }
}

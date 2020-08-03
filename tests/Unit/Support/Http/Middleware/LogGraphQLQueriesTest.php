<?php

namespace Tests\Unit\Support\Http\Middleware;

use Nuwave\Lighthouse\Support\Http\Middleware\LogGraphQLQueries;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class LogGraphQLQueriesTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&\Psr\Log\LoggerInterface
     */
    protected $logger;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make('config');

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
                ]
            );

        $this->graphQL($query);
    }
}

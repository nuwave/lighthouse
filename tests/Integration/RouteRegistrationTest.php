<?php

namespace Tests\Integration;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Tests\TestCase;

final class RouteRegistrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LighthouseServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $config = $app->make(ConfigRepository::class);
        assert($config instanceof ConfigRepository);
        $config->set('lighthouse.route.prefix', 'foo');
    }

    public function testRegisterRouteWithCustomConfig(): void
    {
        $router = $this->app->make(Router::class);
        assert($router instanceof Router);

        $routes = $router->getRoutes();

        $graphqlRoute = $routes->getByName('graphql');
        assert($graphqlRoute instanceof Route);

        $this->assertEquals(
            ['GET', 'POST', 'HEAD'],
            $graphqlRoute->methods()
        );
        $this->assertSame('foo', $graphqlRoute->getPrefix());
    }
}

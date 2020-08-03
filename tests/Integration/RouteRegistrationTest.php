<?php

namespace Tests\Integration;

use Illuminate\Routing\Route;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Orchestra\Testbench\TestCase;

class RouteRegistrationTest extends TestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LighthouseServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app['config'];
        $config->set(
            'lighthouse.route.prefix',
            'foo'
        );
    }

    public function testRegisterRouteWithCustomConfig(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = app('router');
        $routes = $router->getRoutes();

        $graphqlRoute = $routes->getByName('graphql');

        $this->assertInstanceOf(Route::class, $graphqlRoute);
        /** @var \Illuminate\Routing\Route $graphqlRoute */
        $this->assertEquals(
            ['GET', 'POST', 'HEAD'],
            $graphqlRoute->methods()
        );
        $this->assertSame('foo', $graphqlRoute->getPrefix());
    }
}

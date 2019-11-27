<?php

namespace Tests\Integration;

use Nuwave\Lighthouse\LighthouseServiceProvider;
use Orchestra\Testbench\TestCase;

class RouteRegistrationTest extends TestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return string[]
     */
    protected function getPackageProviders($app)
    {
        return [
            LighthouseServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
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
        /** @var \Illuminate\Routing\Router|\Laravel\Lumen\Routing\Router $router */
        $router = app('router');
        $routes = $router->getRoutes();

        $graphqlRoute = $routes->getByName('graphql');
        $this->assertEquals(
            ['GET', 'POST', 'HEAD'],
            $graphqlRoute->methods()
        );
        $this->assertSame('foo', $graphqlRoute->getPrefix());
    }
}

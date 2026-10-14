<?php declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Http\GraphQLController;

$container = Container::getInstance();

$config = $container->make(ConfigRepository::class);
if ($routeConfig = $config->get('lighthouse.route')) {
    /**
     * Not using assert() as only one of those classes will actually be installed.
     *
     * @var Illuminate\Contracts\Routing\Registrar|\Laravel\Lumen\Routing\Router $router
     */
    $router = $container->make('router');

    $action = [
        'as' => $routeConfig['name'] ?? 'graphql',
        'uses' => GraphQLController::class,
    ];

    if (isset($routeConfig['middleware'])) {
        $action['middleware'] = $routeConfig['middleware'];
    }

    if (isset($routeConfig['prefix'])) {
        $action['prefix'] = $routeConfig['prefix'];
    }

    if (isset($routeConfig['domain'])) {
        $action['domain'] = $routeConfig['domain'];
    }

    if (isset($routeConfig['where'])) {
        $action['where'] = $routeConfig['where'];
    }

    $router->addRoute(
        ['GET', 'POST'],
        $routeConfig['uri'] ?? 'graphql',
        $action,
    );
}

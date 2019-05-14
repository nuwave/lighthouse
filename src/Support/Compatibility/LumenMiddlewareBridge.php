<?php

namespace Nuwave\Lighthouse\Support\Compatibility;

use ReflectionClass;
use ReflectionException;
use Laravel\Lumen\Application;

class LumenMiddlewareBridge implements MiddlewareBridge
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function getMiddleware(): array
    {
        $globalMiddleware = self::accessProtected($this->app, 'middleware', []);
        $routeMiddleware = self::accessProtected($this->app, 'routeMiddleware', []);
        return array_merge($globalMiddleware, $routeMiddleware);
    }

    public function getMiddlewareGroups(): array
    {
        return []; // Lumen doesn't have middleware groups
    }

    /**
     * Get the value of a protected member variable of an object.
     *
     * @param object $object Object with protected member
     * @param string $memberName Name of object's protected member
     * @param null|mixed $default Default value to return in case of access error
     * @return mixed Value of object's protected member
     */
    private static function accessProtected($object, $memberName, $default = null)
    {
        try {
            $reflection = new ReflectionClass($object);
            $property = $reflection->getProperty($memberName);
            $property->setAccessible(true);

            return $property->getValue($object);
        } catch (ReflectionException $ex) {
            return $default;
        }
    }
}

<?php

namespace Nuwave\Lighthouse\Support;

use Closure;
use Illuminate\Container\Container as Application;
use InvalidArgumentException;
use Nuwave\Lighthouse\Exceptions\InvalidDriverException;
use ReflectionClass;

/**
 * NOTE: Implementation pulled from \Illuminate\Cache\CacheManager. Purpose is
 * to serve as a base class to easily generate a manager that creates drivers
 * with configuration options.
 */
abstract class DriverManager
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Container\Container
     */
    protected $app;

    /**
     * The array of resolved drivers.
     *
     * @var array<string, object>
     */
    protected $drivers = [];

    /**
     * The registered custom driver creators.
     *
     * @var array<string, \Closure>
     */
    protected $customCreators = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a driver instance by name.
     *
     * @return object the driver instance
     */
    public function driver(?string $name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->drivers[$name] = $this->get($name);
    }

    /**
     * Attempt to get the driver from the local cache.
     *
     * @return object the resolved driver
     */
    protected function get(string $name)
    {
        return $this->drivers[$name] ?? $this->resolve($name);
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->app['config'][$this->driverKey()];
    }

    /**
     * Set the default driver name.
     */
    public function setDefaultDriver(string $name): void
    {
        $this->app['config'][$this->driverKey()] = $name;
    }

    /**
     * Get the driver configuration.
     *
     * @return array<string, mixed>
     */
    protected function getConfig(string $name): array
    {
        return $this->app['config']->get(
            "{$this->configKey()}.{$name}",
            ['driver' => $name]
        );
    }

    /**
     * Register a custom driver creator Closure.
     */
    public function extend(string $driver, Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Resolve the given driver.
     *
     * @throws \InvalidArgumentException
     *
     * @return object the resolved driver
     */
    protected function resolve(string $name)
    {
        $config = $this->getConfig($name);

        if (isset($this->customCreators[$config['driver']])) {
            return $this->validateDriver($this->callCustomCreator($config));
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->validateDriver($this->{$driverMethod}($config));
        }

        throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param  array<string, mixed>  $config
     *
     * @return object the created driver
     */
    protected function callCustomCreator(array $config)
    {
        return $this->customCreators[$config['driver']]($this->app, $config);
    }

    /**
     * Validate driver implements the proper interface.
     *
     * @param  object  $driver
     *
     * @throws \Nuwave\Lighthouse\Exceptions\InvalidDriverException
     *
     * @return object
     */
    protected function validateDriver($driver)
    {
        $interface = $this->interface();

        if (! (new ReflectionClass($driver))->implementsInterface($interface)) {
            throw new InvalidDriverException(get_class($driver) . " does not implement {$interface}");
        }

        return $driver;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  array<mixed>  $parameters
     *
     * @return mixed whatever the driver returned
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }

    /**
     * Get configuration key.
     *
     * @return string
     */
    abstract protected function configKey();

    /**
     * Get configuration driver key.
     *
     * @return string
     */
    abstract protected function driverKey();

    /**
     * The interface the driver should implement.
     *
     * @return string
     */
    abstract protected function interface();
}

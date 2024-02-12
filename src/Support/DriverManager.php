<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support;

use Illuminate\Container\Container as Application;
use Nuwave\Lighthouse\Exceptions\InvalidDriverException;

/**
 * NOTE: Implementation pulled from \Illuminate\Cache\CacheManager. Purpose is
 * to serve as a base class to easily generate a manager that creates drivers
 * with configuration options.
 *
 * @phpstan-type CustomCreator callable(\Illuminate\Container\Container $app, array<string, mixed> $config): object
 */
abstract class DriverManager
{
    /**
     * The array of resolved drivers.
     *
     * @var array<string, object>
     */
    protected array $drivers = [];

    /**
     * The registered custom driver creators.
     *
     * @var array<string, CustomCreator>
     */
    protected array $customCreators = [];

    public function __construct(
        /**
         * The application instance.
         */
        protected Application $app,
    ) {}

    /**
     * Get a driver instance by name.
     *
     * @return object the driver instance
     */
    public function driver(?string $name = null): object
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->drivers[$name] = $this->get($name);
    }

    /**
     * Attempt to get the driver from the local cache.
     *
     * @return object the resolved driver
     */
    protected function get(string $name): object
    {
        return $this->drivers[$name]
            ?? $this->resolve($name);
    }

    /** Get the default driver name. */
    public function getDefaultDriver(): string
    {
        return $this->app['config'][$this->driverKey()];
    }

    /** Set the default driver name. */
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
            ['driver' => $name],
        );
    }

    /**
     * Register a custom driver creator callback.
     *
     * @param  CustomCreator  $callback
     */
    public function extend(string $driver, callable $callback): self
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Resolve the given driver.
     *
     * @return object the resolved driver
     */
    protected function resolve(string $name): object
    {
        $config = $this->getConfig($name);

        if (isset($this->customCreators[$config['driver']])) {
            return $this->validateDriver($this->callCustomCreator($config));
        }

        $upperDriver = ucfirst($config['driver']);
        $driverMethod = "create{$upperDriver}Driver";

        if (method_exists($this, $driverMethod)) {
            return $this->validateDriver($this->{$driverMethod}($config));
        }

        throw new \InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param  array<string, mixed>  $config
     *
     * @return object the created driver
     */
    protected function callCustomCreator(array $config): object
    {
        return $this->customCreators[$config['driver']]($this->app, $config);
    }

    /** Validate driver implements the proper interface. */
    protected function validateDriver(object $driver): object
    {
        $interface = $this->interface();

        if (! $driver instanceof $interface) {
            $driverClass = $driver::class;
            throw new InvalidDriverException("{$driverClass} does not implement {$interface}.");
        }

        return $driver;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  array<mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->driver()->$method(...$parameters);
    }

    /** Get configuration key. */
    abstract protected function configKey(): string;

    /** Get configuration driver key. */
    abstract protected function driverKey(): string;

    /** The interface the driver should implement. */
    abstract protected function interface(): string;
}

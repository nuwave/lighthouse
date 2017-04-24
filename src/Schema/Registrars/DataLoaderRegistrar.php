<?php

namespace Nuwave\Lighthouse\Schema\Registrars;

use Nuwave\Lighthouse\Support\Traits\Container\SchemaClassRegistrar as ClassRegistrar;

class DataLoaderRegistrar
{
    use ClassRegistrar;

    /**
     * Add Data Loader to registrar.
     *
     * @param  string  $name
     * @param  string  $namespace
     * @return bool
     */
    public function register($name, $namespace)
    {
        $loader = $this->getClassName($namespace);

        app()->singleton($loader);
        app()->alias($loader, $this->alias($name));

        return true;
    }

    /**
     * Extract Data Loader from IoC Container.
     *
     * @param  string  $name
     * @return \Nuwave\Lighthouse\Support\DataLoader\GraphQLDataFetcher
     */
    public function instance($name)
    {
        return app($this->alias($name));
    }

    /**
     * Get alias of Data Loader.
     *
     * @param  string  $name
     * @return string
     */
    protected function alias($name)
    {
        return 'graphql.dataloader.'.$name;
    }
}

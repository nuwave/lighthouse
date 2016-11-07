<?php

namespace Nuwave\Lighthouse\Schema\Registrars;

class DataLoaderRegistrar
{
    /**
     * Add Data Loader to registrar.
     *
     * @param  string            $name
     * @param  string $loader
     * @return bool
     */
    public function register($name, $loader)
    {
        app()->singleton($loader);
        app()->alias($loader, $this->alias($name));

        $instance = app($this->alias($name));

        if (empty($instance->getName())) {
            $instance->setName($name);
        }

        return true;
    }

    /**
     * Extract Data Loader from IoC Container.
     *
     * @param  string $name
     * @return \Nuwave\Lighthouse\Support\DataLoader\GraphQLDataLoader
     */
    public function instance($name)
    {
        return app($this->alias($name));
    }

    /**
     * Get alias of Data Loader.
     *
     * @param  string $name
     * @return string
     */
    protected function alias($name)
    {
        return 'graphql.dataloader.'.$name;
    }
}

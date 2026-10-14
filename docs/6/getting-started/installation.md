# Installation

The following section teaches you how to install Lighthouse in your project.

## Install via composer

```shell
composer require nuwave/lighthouse
```

## Publish the default schema

Lighthouse includes a default schema to get you going right away.
Publish it using the following `artisan` command:

```shell
php artisan vendor:publish --tag=lighthouse-schema
```

## Lumen

To use the included lighthouse config, copy it over to your config folder.

```shell
mkdir --parents config
cp vendor/nuwave/lighthouse/src/lighthouse.php config/
```

Register the config file within your `bootstrap/app.php` file:

```php
$app->configure('lighthouse');
```

Register the service provider in your `bootstrap/app.php` file:

```php
$app->register(\Nuwave\Lighthouse\LighthouseServiceProvider::class);
```

The many features Lighthouse provides are split across multiple service providers.
Since Lumen does not support auto-discovery, you will have to register them individually depending on which features you want to use.
Check [Lighthouse's composer.json](https://github.com/nuwave/lighthouse/blob/master/composer.json), the section `extra.laravel.providers` contains the default service providers.

To get you going right away in Lumen, copy over the included default schema.
It uses pagination and validation, so you need to register the service providers.

```shell
mkdir --parents graphql
cp vendor/nuwave/lighthouse/src/default-schema.graphql graphql/schema.graphql
```

```php
$app->register(\Nuwave\Lighthouse\Pagination\PaginationServiceProvider::class);
$app->register(\Nuwave\Lighthouse\Validation\ValidationServiceProvider::class);
```

## IDE Support

Lighthouse makes heavy use of the SDL and uses schema directives.
To improve your editing experience, you can generate a definition file [with an artisan command](../api-reference/commands.md#ide-helper):

```shell
php artisan lighthouse:ide-helper
```

For Phpstorm, we recommend [the GraphQL plugin](https://plugins.jetbrains.com/plugin/8097-graphql).

## Install GraphQL DevTools

To make use of the amazing tooling around GraphQL, we recommend installing [GraphiQL](https://github.com/mll-lab/laravel-graphiql).

```shell
composer require mll-lab/laravel-graphiql
```

After installation, visit `/graphiql` to try it.

You can use any GraphQL client with Lighthouse, make sure to point it to the URL defined in the config.
By default, the endpoint lives at `/graphql`.

# Installation

The following section teaches you how to install Lighthouse in your project.

## Install via composer

```bash
composer require nuwave/lighthouse
```

## Publish the default schema

Lighthouse includes a default schema to get you going right away. Publish
it using the following `artisan` command:

```bash
php artisan vendor:publish --tag=lighthouse-schema
```

## Lumen

Register the service provider in your `bootstrap/app.php` file:

```php
$app->register(\Nuwave\Lighthouse\LighthouseServiceProvider::class);
```

The many features Lighthouse provides are split across multiple service providers.
Since Lumen does not support auto-discovery, you will have to register them individually
depending on which features you want to use. Check [Lighthouse's composer.json](https://github.com/nuwave/lighthouse/blob/master/composer.json),
the section `extra.laravel.providers` contains the default service providers.

## IDE Support

Lighthouse makes heavy use of the SDL and utilizes schema directives.
To improve your editing experience, you can generate a definition file
[with an artisan command](../api-reference/commands.md#ide-helper):

```bash
php artisan lighthouse:ide-helper
```

This command requires `haydenpierce/class-finder`. Install it by running:

```bash
composer require --dev haydenpierce/class-finder
```

We recommend the following plugins:

| IDE      | Plugin                                               |
| -------- | ---------------------------------------------------- |
| PhpStorm | https://plugins.jetbrains.com/plugin/8097-js-graphql |

## Install GraphQL DevTools

To make use of the amazing tooling around GraphQL, we recommend
installing [GraphQL Playground](https://github.com/mll-lab/laravel-graphql-playground).

```bash
composer require mll-lab/laravel-graphql-playground
```

After installation, visit `/graphql-playground` to try it.

You can use any GraphQL client with Lighthouse, make sure to point it to the URL defined in
the config. By default, the endpoint lives at `/graphql`.

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
php artisan vendor:publish --provider="Nuwave\Lighthouse\LighthouseServiceProvider" --tag=schema
```

## Install GraphQL DevTools

To make use of the amazing tooling around GraphQL, we recommend
installing [GraphQL Playground](https://github.com/mll-lab/laravel-graphql-playground)

```bash
composer require mll-lab/laravel-graphql-playground
```

After installation, visit `/graphql-playground` to try it.

You can use any GraphQL client with Lighthouse, make sure to point it to the URL defined in
the config. By default, the endpoint lives at `/graphql`.

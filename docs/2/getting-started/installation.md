# Installation

## Install via composer

```bash
composer require nuwave/lighthouse
```

## Publish the default schema

Lighthouse includes a default schema to get you going right away. Publish
it using the following artisan command:

```bash
php artisan vendor:publish --provider="Nuwave\Lighthouse\Providers\LighthouseServiceProvider" --tag=schema
```

Learn more about how a GraphQL schema works by reading up on the [schema basics](../the-basics/schema.md)

## Use GraphQL DevTools

Lighthouse does not include additional GraphQL tooling, such as the GraphiQL editor.
To integrate a web UI to query your GraphQL endpoint with your Laravel installation, we recommend
installing [GraphQL Playground](https://github.com/mll-lab/laravel-graphql-playground)

```bash
composer require mll-lab/laravel-graphql-playground
```

After installation, visit `/graphql-playground` to try it.

You can also use any external client with Lighthouse, make sure to point it to the URL defined in
the config. Per default, the endpoint lives at `/graphql`

## Using the Facade

If you want to use the GraphQL facade, add it to your `config/app.php` file

```php
'aliases' => [
    // ...
    'GraphQL' => Nuwave\Lighthouse\Support\Facades\GraphQLFacade::class,
]
```

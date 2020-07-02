# Configuration

Lighthouse comes with sensible configuration defaults and works right out of the box.
Should you feel the need to change your configuration, you need to publish the configuration file first.

```bash
php artisan vendor:publish --provider="Nuwave\Lighthouse\LighthouseServiceProvider" --tag=config
```

The configuration file will be placed in `config/lighthouse.php`.

## CORS

A GraphQL API can be consumed from multiple clients, which may or may not reside
on the same domain as your server. Make sure you enable [Cross-Origin Resource Sharing (CORS)](https://laravel.com/docs/routing#cors)
for your GraphQL endpoint in `config/cors.php`:

```diff
return [
-   'paths' => ['api/*'],
+   'paths' => ['api/*', 'graphql'],
    ...
];
```

> CORS is built into Laravel starting from version 7, for previous versions use https://github.com/fruitcake/laravel-cors

# Schema caching

As your schema grows larger, the construction of the schema from raw `.graphql` files becomes more and more costly.

Schema caching is enabled in non-local environments by default, see `config/lighthouse.php`.

## Deployment

Update your cache when deploying a new version of your application using the [cache](../api-reference/commands.md#cache) artisan command:

```shell
php artisan lighthouse:cache
```

The structure of the serialized schema can change between Lighthouse releases.
In order to prevent errors, use a deployment method that atomically updates both the cache file and the dependencies, e.g. K8s.

## Long-running processes

When running in a long-running process such as [Laravel Octane](https://laravel.com/docs/octane),
Lighthouse keeps the contents of the schema cache file in memory and reuses them for subsequent requests
until the file changes.
Enable [OPcache](server-configuration.md#opcache) for the worker processes as well - set `opcache.enable_cli=1` when using Swoole or RoadRunner -
so the cached schema is stored in shared memory instead of each worker's own memory.

## Development

In order to speed up responses during development, change this setting to be always on:

```php
'schema_cache' => [
    'enable' => env('LIGHTHOUSE_SCHEMA_CACHE_ENABLE', true),
],
```

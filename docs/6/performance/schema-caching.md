# Schema caching

As your schema grows larger, the construction of the schema from raw `.graphql` files becomes more and more costly.

Schema caching is enabled in non-local environments by default, see `config/lighthouse.php`.

## Deployment

Update your cache when deploying a new version of your application using the [cache](../api-reference/commands.md#cache) artisan command:

```shell
php artisan lighthouse:cache
```

The structure of the serialized schema can change between Lighthouse releases.
In order to prevent errors, use a deployment method that atomically updates both the cache file and the dependencies, e.g.
K8s.

## Development

In order to speed up responses during development, change this setting to be always on:

```php
'schema_cache' => [
    'enable' => env('LIGHTHOUSE_SCHEMA_CACHE_ENABLE', true),
],
```

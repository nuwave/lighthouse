# Schema caching

As your schema grows larger, the construction of the schema from raw `.graphql` files becomes more and more costly.

Schema caching is enabled in non-local environments by default, see `config/lighthouse.php`.

## Deployment

Update your cache when deploying a new version of your application using the [cache](../api-reference/commands.md#cache) artisan command:

    php artisan lighthouse:cache

The structure of the serialized schema can change between Lighthouse releases.
In order to prevent errors, use cache version 2 and a deployment method that atomically updates both the cache file and the dependencies, e.g.
K8s.

## Development

In order to speed up responses during development, change this setting to be always on:

```php
'cache' => [
    'enable' => env('LIGHTHOUSE_CACHE_ENABLE', true),
],
```

## Leverage OPcache

If you use [OPcache](https://www.php.net/manual/en/book.opcache.php), set cache version to `2` in `config/lighthouse.php`.

```php
'cache' => [
    'enable' => env('LIGHTHOUSE_CACHE_ENABLE', true),
    'version' => env('LIGHTHOUSE_CACHE_VERSION', 2),
],
```

This will store the compiled schema as a PHP file on your disk, allowing OPcache to pick it up.

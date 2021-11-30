# Schema caching

As your schema grows larger, the construction of the schema from raw `.graphql` files
becomes more and more costly.

Make sure to enable schema caching in `config/lighthouse.php` when shipping Lighthouse to production:

```php
'cache' => [
    'enable' => env('LIGHTHOUSE_CACHE_ENABLE', true),
],
```

## Commands

Regenerate your schema cache using the [cache](../api-reference/commands.md#cache) artisan command:

    php artisan lighthouse:cache

Clear the cache without regenerating using the [clear-cache](../api-reference/commands.md#clear-cache) artisan command:

    php artisan lighthouse:clear-cache

## Leverage OPcache

If you use [OPcache](https://www.php.net/manual/en/book.opcache.php), set cache version to `2` in `config/lighthouse.php`.

```php
'cache' => [
    'enable' => env('LIGHTHOUSE_CACHE_ENABLE', true),
    'version' => env('LIGHTHOUSE_CACHE_VERSION', 2),
],
```

This will store the compiled schema as a PHP file on your disk, allowing OPcache to pick it up.

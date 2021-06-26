# Schema caching

As your schema grows larger, the construction of the schema from raw `.graphql` files
becomes more and more costly.

Make sure to enable schema caching when shipping Lighthouse to production.

```php
    /*
    |--------------------------------------------------------------------------
    | Schema Cache
    |--------------------------------------------------------------------------
    |
    | A large part of schema generation is parsing the schema into an AST.
    | This operation is pretty expensive so it is recommended to enable
    | caching in production mode, especially for large schemas.
    |
    */

    'cache' => [
        'enable' => env('LIGHTHOUSE_CACHE_ENABLE', true),
        'key' => env('LIGHTHOUSE_CACHE_KEY', 'lighthouse-schema'),
    ],
```

You may clear your schema cache using the [clear-cache](../api-reference/commands.md#clear-cache) artisan command:

    php artisan lighthouse:clear-cache

## Opcache your schema

If you use opcache in your production environment, you may set the cache version to `2`.
This will store the compiled schema as a file on your disk, allowing opcache to pick it up.

```php
    'cache' => [
        'enable' => env('LIGHTHOUSE_CACHE_ENABLE', true),
        'version' => env('LIGHTHOUSE_CACHE_VERSION', 2), 
    ],
```

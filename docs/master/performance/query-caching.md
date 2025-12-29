# Query caching

In order to speed up GraphQL query parsing, the parsed queries can be stored in the Laravel cache.

## Configuration

Query caching is enabled by default.
You can disable it by setting `query_cache.enable` to `false` in `config/lighthouse.php`.

You can control how query caching works through the option `query_cache.mode` in `config/lighthouse.php`.
Make sure to [clear the query cache](#cache-invalidation) when changing the mode.

### Mode `store`

Use an external shared cache through a Laravel cache store like Redis or Memcached.
This is only recommended if your application can not write to the local filesystem.

### Mode `opcache`

Store parsed queries in PHP files on the local filesystem to leverage OPcache.
This is recommended if your application is running on a single server instance with write access to a persistent local filesystem.

### Mode `hybrid`

Leverage OPcache, but use a shared cache store when local files are not found.
This is recommended if your application is running on multiple server instances with write access to a persistent local filesystem.

## Cache invalidation

You may set the option `query_cache.ttl` in `config/lighthouse.php` to remove cache entries automatically after a given number of seconds.
This is only supported when using an external shared cache through a Laravel cache store like Redis or Memcached.
That way, old queries that are potentially unused will be removed after a while.

When using the modes [`opcache`](#mode-opcache) or [`hybrid`](#mode-hybrid), you need to remove old cached query files manually.
For example, you may run the following command periodically to remove all cached query files older than 24 hours.

```shell
php artisan lighthouse:clear-query-cache --opcache-only --opcache-ttl-hours=24
```

In some scenarios, you may need to clear the query cache completely.

The Artisan command works based on your current configuration for `query_cache` in `config/lighthouse.php`.

- When using the modes [`store`](#mode-store) or [`hybrid`](#mode-hybrid), all entries in the configured cache store will be removed regardless of their age or whether they even belong to Lighthouse.
- When using the modes [`opcache`](#mode-opcache) or [`hybrid`](#mode-hybrid), all cached query files will be removed.

When you plan to change `query_cache.mode`, clear your cache while your current configuration is still in place.

```shell
php artisan lighthouse:clear-query-cache
```

Other reasons to clear the query cache completely include:

- you have stale queries in your cache that have an inappropriate or missing TTL
- you want to free up disk space used by cached query files

## Automated Persisted Queries

Lighthouse supports Automatic Persisted Queries (APQ), compatible with the
[Apollo implementation](https://www.apollographql.com/docs/apollo-server/performance/apq).

APQ is enabled by default, but depends on query caching being enabled.

## Query validation caching

Lighthouse can cache the result of the query validation process as well.
It only caches queries without errors.
`QueryComplexity` validation can not be cached as it depends on runtime variables, so it is always executed.

Query validation caching is disabled by default.
You can enable it by setting `validation_cache.enable` to `true` in `config/lighthouse.php`.

### Cache key components

The validation cache key includes:

- Library versions (`webonyx/graphql-php` and `nuwave/lighthouse`) - cache is automatically invalidated when upgrading
- Schema hash - cache is invalidated when the schema changes
- Query hash - each unique query has its own cache entry
- Rule configuration hash (`max_query_depth`, `disable_introspection`) - cache is invalidated when security settings change

This ensures that cached validation results are automatically invalidated when any of the inputs that affect validation change.
You do not need to manually clear the cache when upgrading these libraries.

## Testing caveats

If you are mocking Laravel cache classes like `Illuminate\Support\Facades\Cache` or `Illuminate\Cache\Repository` and asserting expectations in your unit tests, it might be best to disable the query cache in your `phpunit.xml`:

```diff
  <?xml version="1.0" encoding="UTF-8"?>
  <phpunit ...>
      ...
      <php>
+         <server name="LIGHTHOUSE_QUERY_CACHE_ENABLE" value="false" />
      </php>
  </phpunit>
```

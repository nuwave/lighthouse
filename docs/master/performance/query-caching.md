# Query caching

In order to speed up GraphQL query parsing, the parsed queries can be stored in the Laravel cache.

### Configuration

Query caching is enabled by default.
You can disable it by setting `query_cache.enable` to `false` in `config/lighthouse.php`.

There are two fundamentally different ways of caching queries:

- using an external cache through a Laravel cache store like Redis or Memcached
- using a file based cache that leverages OPcache

Depending on your setup, one or the other might be more suitable.
You can configure which one to use through the option `query_cache.use_file_cache` in `config/lighthouse.php`.

### Cache invalidation

When using an external cache store, you can configure a TTL for cached queries.
That way, old queries that are potentially unused will be removed from the cache automatically.

When using the file based cache, you have to manually remove old cached queries.
Run the following command periodically to remove old files:

```shell
php artisan lighthouse:clear-query-cache --hours=<TTL in hours>
```

Make sure you flush the query cache when you deploy an upgraded version of the `webonyx/graphql-php` dependency.
When using an external cache, remove all keys for the configured store:

```shell
php artisan cache:clear <store name>
```

When using the file based cache, remove all cached query files:

```shell
php artisan lighthouse:clear-query-cache
```

## Automated Persisted Queries

Lighthouse supports Automatic Persisted Queries (APQ), compatible with the
[Apollo implementation](https://www.apollographql.com/docs/apollo-server/performance/apq).

APQ is enabled by default, but depends on query caching being enabled.

## Query validation caching

Lighthouse can cache the result of the query validation process as well.
It only caches queries without errors.
`QueryComplexity` validation can not be cached as it is dependent on the query, so it is always executed.

Query validation caching is disabled by default.
You can enable it by setting `validation_cache.enable` to `true` in `config/lighthouse.php`.

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

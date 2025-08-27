# Query caching

In order to speed up GraphQL query parsing, the parsed queries can be stored in the Laravel cache.

Query caching is enabled by default.
You can define cache store and cache duration, see `config/lighthouse.php`.

Make sure you flush the query cache when you deploy an upgraded version of the `webonyx/graphql-php` dependency:

```shell
php artisan cache:clear
```

## Automated Persisted Queries

Lighthouse supports Automatic Persisted Queries (APQ), compatible with the
[Apollo implementation](https://www.apollographql.com/docs/apollo-server/performance/apq).

APQ is enabled by default, but depends on query caching being enabled.

## File based caching

Similar to how schema caching works by storing the compiled schema in a php file
in your bootstrap/cache directory, you may enable the file based query cache.
This will allow opcache to pick up the compiled queries, and it also reduces
network load, if you have been using redis before.

```dotenv
LIGHTHOUSE_QUERY_CACHE_USE_FILE_CACHE=true
```

One downside with this approach is, that there is no automatic cleanup like with
a TTL in a caching system. You may run this command to regularly remove old query
files from the filesystem.

```shell
# remove all files
php artisan lighthouse:clear-query-cache
# only removes files older than 48 hours
php artisan lighthouse:clear-query-cache --hours=48
```

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

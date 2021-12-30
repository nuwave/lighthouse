# Query caching

In order to speed up the GraphQL query parsing, its results can be stored in the Laravel cache.

Query caching is enabled in non-local environments by default. You can define cache store and cache duration,
see `config/lighthouse.php`.

The GraphQL schema doesn't affect query parsing, so you have to flush query cache only on the package update.

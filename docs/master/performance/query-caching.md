# Query caching

In order to speed up GraphQL query parsing, its results can be stored in the Laravel cache.

Query caching is enabled by default. You can define cache store and cache duration, see `config/lighthouse.php`.

Make sure you flush the query cache when you deploy an upgraded version of the `webonyx/graphql-php` dependency:

    php artisan cache:clear

## Automated persisted queries

Lighthouse supports [Apollo Automatic persisted queries](https://www.apollographql.com/docs/apollo-server/performance/apq/).

It is enabled by default. You can disable it in the config file. The query caching must be enabled.

# Query caching

In order to speed up the GraphQL query parsing, its results can be stored in the Laravel cache.

Query caching is enabled by default. You can define cache store and cache duration, see `config/lighthouse.php`.

Make sure to flush the query cache when you deploy an upgraded version of the dependency `webonyx/graphql-php`:

    php artisan cache:clear

# GraphQL Playground

GraphQL Playground is a IDE for your GraphQL server.\
It helps you with testing/browsing your API, it a easy way with auto-completion.

## Browser
Playground can be installed as a endpoint on your server. This way you
do not need to install anything, but can just open of the endpoint and
use it.\
For installing it in the browser a package for Laravel named [laravel-graphql-playground](https://github.com/mll-lab/laravel-graphql-playground)
is available.

This can be installed via composer 

```bash
composer require mll-lab/laravel-graphql-playground
php artisan vendor:publish --provider="MLL\GraphQLPlayground\GraphQLPlaygroundServiceProvider"
```

After installation, make sure to point it to the URL defined in
the config. By default, the endpoint lives at `/graphql`.\
\
Using the browser version has the advantage of being able to pass sessions as it lives in your Laravel Application.


## Desktop
Playground also have a desktop edition for those of you who prefer that.\
To install it, simply download the [latest release](https://github.com/prisma/graphql-playground/releases/latest) at their GitHub repo.
# Deferred Fields

::: warning
Currently the `@defer` directive requires Apollo Client in alpha.
Track the state of the PR here: https://github.com/apollographql/apollo-client/pull/3686
:::

Deferring fields allows you to prioritize fetching data needed to render the most important content
as fast as possible, and then loading the rest of the page in the background.

Lighthouse's `DeferExtension` adds support for the experimental `@defer` directive
provided by Apollo which you can read more about [here](https://www.apollographql.com/blog/introducing-defer-in-apollo-server-f6797c4e9d6e).

## Setup

Add the service provider to your `config/app.php`

```php
'providers' => [
    \Nuwave\Lighthouse\Defer\DeferServiceProvider::class,
],
```

<br />

![defer_example](https://user-images.githubusercontent.com/1976169/48140644-71e25500-e266-11e8-924b-08ee2f7318d1.gif)
_(image from [https://blog.apollographql.com/introducing-defer-in-apollo-server-f6797c4e9d6e](https://blog.apollographql.com/introducing-defer-in-apollo-server-f6797c4e9d6e))_

# Deferred Fields

::: warning
Currently the `@defer` directive requires Apollo Client in alpha.
Track the state of the PR here: https://github.com/apollographql/apollo-client/pull/3686#issuecomment-555534519
:::

Deferring fields allows you to prioritize fetching data needed to render the most important content
as fast as possible, and then loading the rest of the page in the background.

Lighthouse adds support for the experimental `@defer` directive through an extension.
Read more about it [here](https://www.apollographql.com/blog/introducing-defer-in-apollo-server-f6797c4e9d6e).

## Setup

Add the service provider to your `config/app.php`:

```php
'providers' => [
    \Nuwave\Lighthouse\Defer\DeferServiceProvider::class,
],
```

## Configuration

Consider the configuration options in your `config/lighthouse.php` to prevent
deferred queries from running to long.

<br />

![defer_example](https://user-images.githubusercontent.com/1976169/48140644-71e25500-e266-11e8-924b-08ee2f7318d1.gif)
_(image from [https://blog.apollographql.com/introducing-defer-in-apollo-server-f6797c4e9d6e](https://blog.apollographql.com/introducing-defer-in-apollo-server-f6797c4e9d6e))_

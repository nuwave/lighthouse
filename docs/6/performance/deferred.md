# Deferred Fields

**Experimental: not enabled by default, not guaranteed to be stable.**

Deferring fields allows you to prioritize fetching data needed to render the most important content
as fast as possible, and then loading the rest of the page in the background.

Lighthouse adds support for [the `@defer` directive](https://github.com/graphql/graphql-wg/blob/main/rfcs/DeferStream.md) through an extension.

## Setup

Register the service provider `Nuwave\Lighthouse\Defer\DeferServiceProvider`,
see [registering providers in Laravel](https://laravel.com/docs/providers#registering-providers).

## Configuration

Consider the configuration options under `defer` in your `config/lighthouse.php`
to prevent deferred queries from running to long.

<br />

![defer_example](https://user-images.githubusercontent.com/1976169/48140644-71e25500-e266-11e8-924b-08ee2f7318d1.gif)
_(image from [https://blog.apollographql.com/introducing-defer-in-apollo-server-f6797c4e9d6e](https://blog.apollographql.com/introducing-defer-in-apollo-server-f6797c4e9d6e))_

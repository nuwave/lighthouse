# Extending Lighthouse

Lighthouse offers various extension points which can be utilized by package developers
as well as end users.

## The Event System

Lighthouse offers a unified way of hooking into the complete execution lifecycle
through [Laravel's event system](https://laravel.com/docs/events).
You may use any Service Provider to register listeners.

A complete list of all dispatched events is available [in the events API reference](../api-reference/events.md).

## Adding Directives

Add your custom directives to Lighthouse by listening for the [`RegisterDirectiveNamespaces`](../api-reference/events.md#registerdirectivenamespaces) event.

```php
namespace SomeVendor\SomePackage;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

final class SomePackageServiceProvider extends ServiceProvider
{
    public function boot(EventsDispatcher $dispatcher): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            // May also return an iterable with multiple strings if needed
            static fn (): string => 'SomeVendor\SomePackage\Directives',
        );
    }
```

## Changing the default resolver

Lighthouse will fall back to using [webonyx's default resolver](https://webonyx.github.io/graphql-php/data-fetching/#default-field-resolver)
for non-root fields, [see resolver precedence](../the-basics/fields.md#resolver-precedence).
You may overwrite this by passing a `callable` to `GraphQL\Executor\Executor::setDefaultFieldResolver()`.

## Use a custom `GraphQLContext`

The context is the third argument of any resolver function.

You may replace the default `\Nuwave\Lighthouse\Schema\Context` with your own
implementation of the interface `Nuwave\Lighthouse\Support\Contracts\GraphQLContext`.
The following example is just a starting point of what you can do:

```php
namespace Nuwave\Lighthouse\Schema;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class MyContext implements GraphQLContext
{
    public function __construct(
        public Request $request
    ) {}

    public function request(): Request
    {
        return $this->request;
    }

    public function user(): ?Authenticatable
    {
        // TODO implement yourself
    }
}
```

You need a factory that creates an instance of `\Nuwave\Lighthouse\Support\Contracts\GraphQLContext`.
This factory class needs to implement `\Nuwave\Lighthouse\Support\Contracts\CreatesContext`.

```php
namespace App\GraphQL;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class MyContextFactory implements CreatesContext
{
    public function generate(Request $request): GraphQLContext
    {
        return new MyContext($request);
    }
}
```

Rebind the interface in a service provider (e.g. your `AppServiceProvider` or a new `GraphQLServiceProvider`):

```php
public function register(): void
{
    $this->app->bind(
        \Nuwave\Lighthouse\Support\Contracts\CreatesContext::class,
        \App\MyContextFactory::class
    );
}
```

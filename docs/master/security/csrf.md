# CSRF Protection

To learn about cross-site request forgeries, see [Laravel docs](https://laravel.com/docs/csrf).

Lighthouse provides mitigation against CSRF attacks through the `Nuwave\Lighthouse\Http\Middleware\EnsureXHR` middleware.
Add it as the first middleware for the Lighthouse route in `config/lighthouse.php`:

```php
    'route' => [
        // ...
        'middleware' => [
            Nuwave\Lighthouse\Http\Middleware\EnsureXHR::class,

            // ... other middleware
        ],
    ],
```

It forbids:

- `GET` requests
- `POST` requests that can be created using HTML forms

It allows:

- other request methods
- `POST` requests with the header `X-Requested-With: XMLHttpRequest`
- `POST` requests with a `Content-Type` that can not be set from HTML forms

Caveats:

- Old browsers (IE 9, Opera 12) don't support XHR requests
- You won't be able to use GraphQL queries through `GET` requests or HTML forms

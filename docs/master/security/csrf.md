# CSRF Protection

To learn about cross-site request forgeries, see [Laravel docs](https://laravel.com/docs/csrf).

Lighthouse provides mitigation against CSRF attacks through the `Nuwave\Lighthouse\Support\Http\Middleware\EnsureXHR`
middleware. Just add it as the first middleware for the Lighthouse route in `config/lighthouse.php`:

```php
    'route' => [
        // ...
        'middleware' => [
            Nuwave\Lighthouse\Support\Http\Middleware\EnsureXHR::class,

            // ... other middleware
        ],
    ],
```

It forbids `GET` requests, and `POST` requests which can be created using HTML forms.
Other request types and `POST` requests with a `Content-Type` that can not be set
from HTML forms are passed along.

Caveats:

- Old browsers (IE 9, Opera 12) don't support XHR requests
- You won't be able to use GraphQL queries through `GET` requests or HTML forms

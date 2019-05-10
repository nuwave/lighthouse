# Setup

Install the [Pusher PHP Library](https://github.com/pusher/pusher-http-php) for interacting with the Pusher HTTP API.

    composer require pusher/pusher-php-server

Add the service provider to your `config/app.php`

```php
'providers' => [
    \Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider::class,
],
```

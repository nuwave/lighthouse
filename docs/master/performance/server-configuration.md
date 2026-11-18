# Server configuration

You can tune the configuration of your PHP server for Lighthouse.

## OPcache

The nature of the schema operations in Lighthouse plays nicely with [PHP's OPcache](https://php.net/manual/en/book.opcache.php).
If you can install it on your server, it improves performance.

## Xdebug

Enabling Xdebug and having an active debug session slows down execution by an order of magnitude.

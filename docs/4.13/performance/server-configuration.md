# Server configuration

You can tune the configuration of your PHP server for Lighthouse.

## OPcache

The nature of the schema operations in Lighthouse plays nicely with [PHP's OPcache](https://php.net/manual/en/book.opcache.php).
If you have the freedom to install it on your server, it's an easy way to get a nice performance boost.

## Xdebug

Enabling Xdebug and having an active debug session slows down execution by
an order of magnitude.

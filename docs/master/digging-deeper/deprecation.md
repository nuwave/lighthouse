# Deprecation

The [@deprecated](../api-reference/directives.md#deprecated) directive allows marking elements
of a GraphQL schema as deprecated.

## Detect deprecated usage

Before you eventually remove deprecated elements, you want to give clients time to switch over.
To be sure the elements are no longer in use, dynamic instrumentation is required.

Lighthouse allows you to register a handler function that is called with a list of deprecated
elements that were used in a query. Use a reporting mechanism of your choice to get notified.
In order to not slow down your response times, use a terminating callback.

```php
// Preferably in a service provider
use Nuwave\Lighthouse\Deprecation\DetectDeprecatedUsage;

DetectDeprecatedUsage::handle(function (array $deprecations): void {
    app()->terminating(function () use ($deprecations) {
        foreach ($deprecations as $element => $_) {
            someMethodToReportDeprecations("Deprecated GraphQL element used: {$element}.");
        }
    });
})
```

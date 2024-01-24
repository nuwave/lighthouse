# Deprecation

The [@deprecated](../api-reference/directives.md#deprecated) directive allows marking elements
of a GraphQL schema as deprecated.

## Detect deprecated usage

**Experimental: not enabled by default, not guaranteed to be stable.**

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
        foreach ($deprecations as $element => $deprecatedUsage) {
            someMethodToReportDeprecations("Deprecated GraphQL element {$element} used {$deprecatedUsage->count} times. {$deprecatedUsage->reason}");
        }
    });
});
```

Lighthouse can currently detect the following deprecated elements:

- requested fields
- enum values provided as literals in the GraphQL query string

It does not recognize or warn about:

- enum values provided as variables
- enum values returned as results

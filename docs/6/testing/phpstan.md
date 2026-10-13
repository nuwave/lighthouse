# Static Analysis with PHPStan

Lighthouse encourages the usage of static analysis with [PHPStan](https://phpstan.org).

## Stub Files

::: warning
Using `_ide_helper.php` as a stub file is deprecated and will be removed in v7.
With [Larastan](https://github.com/larastan/larastan) 3.12 or later, it causes the error `class.duplicate`.
Larastan recognizes the mixin methods without the stub file, so remove it from your configuration.
:::

Lighthouse enhances some classes defined in other projects through mixins.
Without Larastan, configure PHPStan to recognize the generated file as a stub:

```neon
# phpstan.neon
parameters:
  stubFiles:
  - vendor/nuwave/lighthouse/_ide_helper.php
```

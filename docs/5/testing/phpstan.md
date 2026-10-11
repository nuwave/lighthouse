# Static Analysis with PHPStan

Lighthouse encourages the usage of static analysis with [PHPStan](https://phpstan.org).

## Stub Files

Lighthouse enhances some classes defined in other projects through mixins.
Configure PHPStan to recognize the generated file as a stub:

```neon
# phpstan.neon
parameters:
  stubFiles:
  - vendor/nuwave/lighthouse/_ide_helper.php
```

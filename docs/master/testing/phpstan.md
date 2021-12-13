# Static Analysis with PHPStan

Lighthouse encourages the usage of static analysis with [PHPStan](https://phpstan.org).

## Stub Files

Lighthouse enhances some classes defined in other projects through mixins.
In order for PHPStan to recognize those additions, first generate the stub files
with [the `ide-helper` command](../api-reference/commands.md#ide-helper).

    php artisan lighthouse:ide-helper

Then, configure PHPStan to recognize the generated file as a stub:

```neon
# phpstan.neon
parameters:
  stubFiles:
  - _lighthouse_ide_helper.php
```

# Automated Code Refactoring with Rector

Lighthouse provides [Rector](https://getrector.com) rules to automate code migrations and enforce conventions.

## RootResolverSignatureRector

Ensures that root resolver `__invoke` methods match the [Lighthouse calling convention](../api-reference/resolvers.md#resolver-function-signature).

Root resolvers receive `null` as their first argument because they have no parent value.
This rule normalizes signatures to explicitly reflect that.

### Setup

Add the rule to your `rector.php` configuration:

```php
use Nuwave\Lighthouse\Rector\RootResolverSignatureRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(RootResolverSignatureRector::class);

    // Required: Larastan bootstrap makes config() available
    $rectorConfig->bootstrapFiles([
        __DIR__ . '/vendor/larastan/larastan/bootstrap.php',
    ]);
};
```

### What it does

> The examples below use `null $root` (PHP 8.2+).
> On PHP 8.0/8.1, the rule produces `mixed $root` instead.

**Adds the missing `$root` parameter** when only `array $args` is present:

```php
// Before
public function __invoke(array $args): mixed {}

// After
public function __invoke(null $root, array $args): mixed {}
```

**Fixes the root parameter type** when it is typed incorrectly:

```php
// Before
public function __invoke(User $root, array $args): mixed {}

// After
public function __invoke(null $root, array $args): mixed {}
```

**Removes a useless single root parameter** when only a non-array root is passed:

```php
// Before
public function __invoke(mixed $root): mixed {}

// After
public function __invoke(): mixed {}
```

**Normalizes parameter names** via the `paramNames` option (see [Configuration](#configuration)).

### Configuration

Optionally configure preferred parameter names:

```php
$rectorConfig->ruleWithConfiguration(RootResolverSignatureRector::class, [
    'paramNames' => ['_', 'args', 'context', 'resolveInfo'],
]);
```

Use `null` at any position to skip renaming that parameter:

```php
$rectorConfig->ruleWithConfiguration(RootResolverSignatureRector::class, [
    'paramNames' => [null, null, 'context', 'resolveInfo'],
]);
```

### Known Limitation

The rule assumes every class with an `__invoke` method found under the resolver directories (e.g. `App\GraphQL\Queries`, `App\GraphQL\Mutations`) is a root resolver.

Classes that are **not** root resolvers — such as nested field resolvers that receive a parent model as their first argument — will be incorrectly rewritten.

**Mitigations:**

- Move non-root-resolver classes out of the resolver directories (preferred — they do not belong there).
- Add the affected files to the Rector [skip list](https://getrector.com/documentation/ignoring-rules-or-paths).

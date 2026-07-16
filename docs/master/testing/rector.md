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

return RectorConfig::configure()
    ->withRules([
        RootResolverSignatureRector::class,
    ]);
```

### What it does

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

You can customize the expected parameter names:

```php
use Nuwave\Lighthouse\Rector\RootResolverSignatureRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withConfiguredRule(RootResolverSignatureRector::class, [
        'paramNames' => ['_', 'args', 'context', 'resolveInfo'],
    ]);
```

The `paramNames` array maps parameter positions (0-indexed) to expected names.
Use `null` to skip renaming a specific position.

### Known Limitation

The rule assumes every class with an `__invoke` method found under the resolver directories (e.g. `App\GraphQL\Queries`, `App\GraphQL\Mutations`) is a root resolver.

Classes that are **not** root resolvers — such as nested field resolvers that receive a parent model as their first argument — will be incorrectly rewritten.

**Mitigations:**

- Move non-root-resolver classes out of the resolver directories (preferred — they do not belong there).
- Add the affected files to the Rector [skip list](https://getrector.com/documentation/ignoring-rules-or-paths).

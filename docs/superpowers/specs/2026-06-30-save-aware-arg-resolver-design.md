# SaveAwareArgResolver Design

## Problem

`@upsert`, `@create` and `@update` on `INPUT_FIELD_DEFINITION` always run post-save.
When the field targets a BelongsTo relation, they need to run before the parent saves so the FK is available.
Custom directives that set model attributes from complex input (e.g. geocoding) also need pre-save timing without being relation-bound.

## Constraints

- `ArgResolver::__invoke(mixed $root, mixed $value)` -- root is `mixed`, not always a Model.
- Pre/post-save is only meaningful inside `SaveModel`'s orchestration.
- Post-save must stay default for backwards compatibility.
- A static marker interface doesn't work for `@upsert` because the same directive handles both BelongsTo (pre) and HasMany (post).
- limes-api extends `ArgPartitioner` with a custom partitioner -- the system is used beyond `SaveModel`.

## Interface

```php
namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * PHPDoc TBD describes when this interface may be needed
 * @api 
 */
interface SaveAwareArgResolver extends ArgResolver
{
    /** PHPDoc TBD — describes when the orchestrator calls this and what true/false means. */
    public function runBeforeSave(Model $model): bool;
}
```

- Extends `ArgResolver` -- a specialization, not a replacement.
- `@api` -- stability guarantee, consumers can implement this. `ArgResolver` also gets `@api`
- Receives the Model so the decision can be contextual (relation type introspection).
- Replaces `PreSaveArgResolver` (branch-only, never shipped).

## Implementors

### ModelMutationDirective

Extracts a `relationName()` method (reused by `__invoke()` and `runBeforeSave()`):

```php
protected function relationName(): string
{
    return $this->directiveArgValue('relation', $this->nodeName());
}

public function runBeforeSave(Model $model): bool
{
    return ArgPartitioner::methodReturnsRelation(
        new \ReflectionClass($model),
        $this->relationName(),
        BelongsTo::class,
    );
}
```

BelongsTo (MorphTo extends from it) returns true (pre-save), everything else returns false (post-save).

### Custom directives (e.g. @geocode test fixture)

```php
public function runBeforeSave(Model $model): bool
{
    return true;
}
```

Always pre-save -- sets attributes on the model from complex input.

## Orchestration

### nestedArgResolvers (name kept)

Extended to exclude `SaveAwareArgResolver` resolvers where `runBeforeSave()` returns true.
Only checks when root is a Model -- non-Model contexts skip the check entirely:

```php
/** PHPDoc TBD clarify the implicit post-save default (explicit for SaveAware) */
public static function nestedArgResolvers(ArgumentSet $argumentSet, mixed $root): array
{
    // ... attach resolvers (unchanged) ...

    return static::partition(
        $argumentSet,
        static function (string $name, Argument $argument) use ($root): bool {
            $resolver = $argument->resolver;
            if ($resolver === null) {
                return false;
            }

            if ($resolver instanceof SaveAwareArgResolver
                && $root instanceof Model
                && $resolver->runBeforeSave($root)
            ) {
                return false;
            }

            return true;
        },
    );
}
```

### SaveModel

Extracts pre-save resolvers and runs them before `$model->save()`:

```php
[$preSave, $remaining] = ArgPartitioner::preSaveNestedArgResolvers($remaining, $model);

// ... fill model, resolve implicit BelongsTo/MorphTo ...

foreach ($preSave->arguments as $nested) {
    $resolver = $nested->resolver;
    assert($resolver instanceof SaveAwareArgResolver);
    $resolver($model, $nested->value);
}

// ... save ...
```

Pre-save extraction happens before implicit `relationMethods(BelongsTo)` so directive-annotated fields aren't captured by name-based relation detection.

### Non-Model contexts (@nest)

When root is not a Model, `SaveAwareArgResolver` resolvers are treated as regular post-save.
The interface is inert outside a Model/save context.

## Test Coverage

1. **BelongsTo via @upsert on INPUT_FIELD_DEFINITION** -- creates the related model and associates FK before parent saves.
2. **@geocode custom directive** -- non-relation `SaveAwareArgResolver` that always returns true. Takes complex input, sets lat/lng on the model. Verifies attributes are present in a single save.
3. **SaveAwareArgResolver inside @nest** -- root is not a Model. Verifies the resolver still runs (post-save path), doesn't crash.
4. **Existing @upsert on HasMany** -- continues to pass (same directive, post-save timing).

## Migration & BC

From master:

- **nestedArgResolvers** -- name kept, logic extended with `SaveAwareArgResolver` check.
- **SaveModel** -- gains pre-save extraction and execution loop.
- **ModelMutationDirective** -- implements `SaveAwareArgResolver`, adds `relationName()`.

Added:

- `SaveAwareArgResolver` interface (new, `@api`).
- `@geocode` test fixture directive.
- Tests for all three scenarios above.

Removed (branch-only, never shipped):

- `PreSaveArgResolver` interface.
- `@connectRelated` test fixture directive.
- `preSaveArgResolvers()` static method (folded into `nestedArgResolvers`).
- The rename to `postSaveArgResolvers` in `ResolveNested`.

Unchanged:

- `nestedArgResolvers` name and signature.
- `ResolveNested` default partitioner reference.
- `ArgResolver` interface.
- Implicit relation detection in `attachNestedArgResolver`.
- limes-api's extended `ArgPartitioner` -- unaffected.

## Changelog

`Added` -- `SaveAwareArgResolver` interface for directives that need control over pre/post-save timing in mutations.

# SaveAwareArgResolver Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the branch-only `PreSaveArgResolver` with `SaveAwareArgResolver` — an interface that lets directives dynamically decide pre/post-save timing based on the Model context.

**Architecture:** `SaveAwareArgResolver extends ArgResolver` with a single `runBeforeSave(Model $model): bool` method. `ArgPartitioner::nestedArgResolvers()` excludes resolvers that return true (passing them through to `SaveModel`). `SaveModel` extracts and invokes them before `$model->save()`. `ModelMutationDirective` implements the interface using relation-type introspection.

**Tech Stack:** PHP 8.2, Laravel/Eloquent, PHPUnit, PHPStan level 8

**User decisions (already made):**
- "Keep `nestedArgResolvers` name — avoid the rename to `postSaveArgResolvers`"
- "Interface should be `SaveAwareArgResolver` with `runBeforeSave(Model $model): bool`"
- "Use `@geocode` test fixture for non-relation pre-save case"
- "Relation-name logic extracted into `relationName()` method on ModelMutationDirective"

---

### Task 1: Reset branch to master baseline for affected files

**Goal:** Remove all branch-only `PreSaveArgResolver` code so we start clean from master for the new design.

**Files:**
- Delete: `src/Support/Contracts/PreSaveArgResolver.php`
- Delete: `tests/Utils/Directives/ConnectRelatedDirective.php`
- Delete: `tests/Unit/Execution/Arguments/Fixtures/PreNested.php`
- Revert: `src/Execution/Arguments/ArgPartitioner.php` (to master)
- Revert: `src/Execution/Arguments/ResolveNested.php` (to master)
- Revert: `src/Execution/Arguments/SaveModel.php` (to master)
- Revert: `src/Support/Contracts/ArgResolver.php` (to master)
- Revert: `tests/Unit/Execution/Arguments/ArgPartitionerTest.php` (to master)
- Revert: `tests/Integration/Schema/Directives/CreateDirectiveTest.php` (to master)

**Acceptance Criteria:**
- [ ] `PreSaveArgResolver.php` no longer exists
- [ ] `ConnectRelatedDirective.php` no longer exists
- [ ] `PreNested.php` no longer exists
- [ ] `ArgPartitioner.php`, `ResolveNested.php`, `SaveModel.php` match master
- [ ] `ArgResolver.php` matches master (no `@api` yet — added in Task 2)
- [ ] Tests pass: `vendor/bin/phpunit tests/Unit/Execution/Arguments/ArgPartitionerTest.php`

**Verify:** `docker compose run --rm php vendor/bin/phpunit tests/Unit/Execution/Arguments/ArgPartitionerTest.php` → PASS

**Steps:**

- [ ] **Step 1: Revert src files to master**

```bash
git -C /home/bfranke/projects/lighthouse checkout master -- \
  src/Execution/Arguments/ArgPartitioner.php \
  src/Execution/Arguments/ResolveNested.php \
  src/Execution/Arguments/SaveModel.php \
  src/Support/Contracts/ArgResolver.php \
  tests/Unit/Execution/Arguments/ArgPartitionerTest.php \
  tests/Integration/Schema/Directives/CreateDirectiveTest.php
```

- [ ] **Step 2: Delete branch-only files**

```bash
git -C /home/bfranke/projects/lighthouse rm \
  src/Support/Contracts/PreSaveArgResolver.php \
  tests/Utils/Directives/ConnectRelatedDirective.php \
  tests/Unit/Execution/Arguments/Fixtures/PreNested.php
```

- [ ] **Step 3: Run tests to verify clean state**

Run: `docker compose run --rm php vendor/bin/phpunit tests/Unit/Execution/Arguments/ArgPartitionerTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git -C /home/bfranke/projects/lighthouse add -A && git -C /home/bfranke/projects/lighthouse commit -m "Remove PreSaveArgResolver in preparation for SaveAwareArgResolver"
```

---

### Task 2: Create SaveAwareArgResolver interface

**Goal:** Define the new interface with `@api` stability guarantee.

**Files:**
- Create: `src/Support/Contracts/SaveAwareArgResolver.php`
- Modify: `src/Support/Contracts/ArgResolver.php` (add `@api`)

**Acceptance Criteria:**
- [ ] Interface extends `ArgResolver`
- [ ] Has `runBeforeSave(Model $model): bool` method
- [ ] Both `ArgResolver` and `SaveAwareArgResolver` have `@api` annotation
- [ ] PHPStan passes

**Verify:** `docker compose run --rm php vendor/bin/phpstan analyse src/Support/Contracts/SaveAwareArgResolver.php src/Support/Contracts/ArgResolver.php` → OK

**Steps:**

- [ ] **Step 1: Add `@api` to ArgResolver**

In `src/Support/Contracts/ArgResolver.php`, add `@api` to the class docblock:

```php
<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

/** @api */
interface ArgResolver
{
    /**
     * @param  mixed  $root  the result of the parent resolver
     * @param  mixed|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $value  the slice of arguments that belongs to this nested resolver
     *
     * @return mixed|void|null May return the modified $root
     */
    public function __invoke(mixed $root, mixed $value);
}
```

- [ ] **Step 2: Create SaveAwareArgResolver**

Create `src/Support/Contracts/SaveAwareArgResolver.php`:

```php
<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Implement this on ArgResolver directives that need to control whether they
 * run before or after the parent model is saved during mutation execution.
 *
 * @api
 */
interface SaveAwareArgResolver extends ArgResolver
{
    /**
     * Should this resolver run before the parent model is persisted?
     *
     * When true, the resolver is invoked before $model->save(), allowing it
     * to set attributes or foreign keys on the model.
     * When false, the resolver runs after the model is saved (the default
     * for any ArgResolver that does not implement this interface).
     *
     * Only consulted when the root is a Model inside SaveModel's orchestration.
     * In non-Model contexts (e.g. @nest), this method is not called and the
     * resolver runs in the default post-save position.
     */
    public function runBeforeSave(Model $model): bool;
}
```

- [ ] **Step 3: Run PHPStan**

Run: `docker compose run --rm php vendor/bin/phpstan analyse src/Support/Contracts/`
Expected: OK (no errors)

- [ ] **Step 4: Commit**

```bash
git -C /home/bfranke/projects/lighthouse add src/Support/Contracts/SaveAwareArgResolver.php src/Support/Contracts/ArgResolver.php && git -C /home/bfranke/projects/lighthouse commit -m "Add SaveAwareArgResolver interface"
```

---

### Task 3: Implement SaveAwareArgResolver in ModelMutationDirective

**Goal:** Make `@upsert`, `@create`, and `@update` dynamically decide pre/post-save timing based on relation type.

**Files:**
- Modify: `src/Schema/Directives/ModelMutationDirective.php`

**Acceptance Criteria:**
- [ ] `ModelMutationDirective` implements `SaveAwareArgResolver`
- [ ] New `relationName(): string` method extracted from `__invoke()`
- [ ] `runBeforeSave()` returns true for BelongsTo relations (which includes MorphTo)
- [ ] `__invoke()` uses `$this->relationName()` instead of inline logic
- [ ] PHPStan passes

**Verify:** `docker compose run --rm php vendor/bin/phpstan analyse src/Schema/Directives/ModelMutationDirective.php` → OK

**Steps:**

- [ ] **Step 1: Add interface and implement**

Modify `src/Schema/Directives/ModelMutationDirective.php`:

```php
<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Execution\Arguments\ArgPartitioner;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNested;
use Nuwave\Lighthouse\Execution\TransactionalMutations;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\SaveAwareArgResolver;
use Nuwave\Lighthouse\Support\Utils;

abstract class ModelMutationDirective extends BaseDirective implements FieldResolver, SaveAwareArgResolver
{
    public function __construct(
        protected TransactionalMutations $transactionalMutations,
    ) {}

    protected function relationName(): string
    {
        return $this->directiveArgValue(
            'relation',
            $this->nodeName(),
        );
    }

    public function runBeforeSave(Model $model): bool
    {
        return ArgPartitioner::methodReturnsRelation(
            new \ReflectionClass($model),
            $this->relationName(),
            BelongsTo::class,
        );
    }

    /**
     * @param  Model  $model
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $args
     *
     * @return \Illuminate\Database\Eloquent\Model|array<\Illuminate\Database\Eloquent\Model>
     */
    public function __invoke($model, $args): mixed
    {
        $relation = $model->{$this->relationName()}();
        assert($relation instanceof Relation);

        $related = $relation->make(); // @phpstan-ignore method.notFound (Relation delegates to Builder)

        return $this->executeMutation($related, $args, $relation);
    }

    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $args
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $parentRelation
     *
     * @return \Illuminate\Database\Eloquent\Model|array<\Illuminate\Database\Eloquent\Model>
     */
    protected function executeMutation(Model $model, ArgumentSet|array $args, ?Relation $parentRelation = null): Model|array
    {
        $update = new ResolveNested($this->makeExecutionFunction($parentRelation));

        return Utils::mapEach(
            static fn (ArgumentSet $argumentSet): mixed => $update($model->newInstance(), $argumentSet),
            $args,
        );
    }

    /**
     * Prepare the execution function for a mutation on a model.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $parentRelation
     */
    abstract protected function makeExecutionFunction(?Relation $parentRelation = null): callable;
}
```

- [ ] **Step 2: Run PHPStan**

Run: `docker compose run --rm php vendor/bin/phpstan analyse src/Schema/Directives/ModelMutationDirective.php`
Expected: OK

- [ ] **Step 3: Commit**

```bash
git -C /home/bfranke/projects/lighthouse add src/Schema/Directives/ModelMutationDirective.php && git -C /home/bfranke/projects/lighthouse commit -m "Implement SaveAwareArgResolver in ModelMutationDirective"
```

---

### Task 4: Update ArgPartitioner and SaveModel orchestration

**Goal:** Make `nestedArgResolvers` exclude pre-save resolvers (passing them to SaveModel), and make SaveModel extract and invoke them before save.

**Files:**
- Modify: `src/Execution/Arguments/ArgPartitioner.php`
- Modify: `src/Execution/Arguments/SaveModel.php`

**Acceptance Criteria:**
- [ ] `nestedArgResolvers` excludes `SaveAwareArgResolver` resolvers where `runBeforeSave($root)` is true (only when root is Model)
- [ ] New `preSaveNestedArgResolvers(ArgumentSet, Model): array` method on ArgPartitioner
- [ ] `SaveModel` extracts pre-save resolvers before implicit BelongsTo detection
- [ ] `SaveModel` invokes pre-save resolvers before `$model->save()`
- [ ] PHPStan passes for both files

**Verify:** `docker compose run --rm php vendor/bin/phpstan analyse src/Execution/Arguments/ArgPartitioner.php src/Execution/Arguments/SaveModel.php` → OK

**Steps:**

- [ ] **Step 1: Update ArgPartitioner::nestedArgResolvers**

In `src/Execution/Arguments/ArgPartitioner.php`, modify the `nestedArgResolvers` method and add `preSaveNestedArgResolvers`:

```php
use Nuwave\Lighthouse\Support\Contracts\SaveAwareArgResolver;
```

Replace the `nestedArgResolvers` method:

```php
    /**
     * Partition the arguments into nested (post-save) and regular.
     *
     * Resolvers implementing SaveAwareArgResolver that return true from
     * runBeforeSave() are excluded from the nested set when the root is a Model,
     * allowing SaveModel to handle them before persisting.
     *
     * @return array{
     *   0: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet,
     *   1: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet,
     * }
     */
    public static function nestedArgResolvers(ArgumentSet $argumentSet, mixed $root): array
    {
        $model = $root instanceof Model
            ? new \ReflectionClass($root)
            : null;

        foreach ($argumentSet->arguments as $name => $argument) {
            static::attachNestedArgResolver($name, $argument, $model);
        }

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

    /**
     * Partition arguments into those with a pre-save resolver and the rest.
     *
     * @return array{
     *   0: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet,
     *   1: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet,
     * }
     */
    public static function preSaveNestedArgResolvers(ArgumentSet $argumentSet, Model $model): array
    {
        return static::partition(
            $argumentSet,
            static fn (string $name, Argument $argument): bool => $argument->resolver instanceof SaveAwareArgResolver
                && $argument->resolver->runBeforeSave($model),
        );
    }
```

- [ ] **Step 2: Update SaveModel**

In `src/Execution/Arguments/SaveModel.php`, add the import and pre-save logic:

```php
use Nuwave\Lighthouse\Support\Contracts\SaveAwareArgResolver;
```

Replace the `__invoke` method body:

```php
    /**
     * @param  Model  $model
     * @param  ArgumentSet  $args
     */
    public function __invoke($model, $args): Model
    {
        [$preSave, $remaining] = ArgPartitioner::preSaveNestedArgResolvers($args, $model);

        // Extract $morphTo first, as MorphTo extends BelongsTo
        [$morphTo, $remaining] = ArgPartitioner::relationMethods(
            $remaining,
            $model,
            MorphTo::class,
        );

        [$belongsTo, $remaining] = ArgPartitioner::relationMethods(
            $remaining,
            $model,
            BelongsTo::class,
        );

        $argsToFill = $remaining->toArray();

        // Use all the remaining attributes and fill the model
        if (config('lighthouse.force_fill')) {
            $model->forceFill($argsToFill);
        } else {
            $model->fill($argsToFill);
        }

        foreach ($belongsTo->arguments as $relationName => $nestedOperations) {
            $belongsTo = $model->{$relationName}();
            assert($belongsTo instanceof BelongsTo);
            $belongsToResolver = new ResolveNested(new NestedBelongsTo($belongsTo));
            $belongsToResolver($model, $nestedOperations->value);
        }

        foreach ($morphTo->arguments as $relationName => $nestedOperations) {
            $morphTo = $model->{$relationName}();
            assert($morphTo instanceof MorphTo);
            $morphToResolver = new ResolveNested(new NestedMorphTo($morphTo));
            $morphToResolver($model, $nestedOperations->value);
        }

        foreach ($preSave->arguments as $nested) {
            $resolver = $nested->resolver;
            assert($resolver instanceof SaveAwareArgResolver, 'Resolver must be a SaveAwareArgResolver because we partitioned for it.');
            $resolver($model, $nested->value);
        }

        if ($this->parentRelation instanceof HasOneOrMany) {
            // If we are already resolving a nested create, we might
            // already have an instance of the parent relation available.
            // In that case, use it to set the current model as a child.
            $this->parentRelation->save($model);

            return $model;
        }

        $model->save();

        if ($this->parentRelation instanceof BelongsTo) {
            $childModel = $this->parentRelation->associate($model);

            // If the child Model does not exist (still to be saved),
            // a save could break any pending belongsTo relations that still
            // need to be created and associated with it.
            if ($childModel->exists) {
                $childModel->save();
            }
        }

        if ($this->parentRelation instanceof BelongsToMany) {
            $this->parentRelation->syncWithoutDetaching($model);
        }

        return $model;
    }
```

- [ ] **Step 3: Run PHPStan**

Run: `docker compose run --rm php vendor/bin/phpstan analyse src/Execution/Arguments/ArgPartitioner.php src/Execution/Arguments/SaveModel.php`
Expected: OK

- [ ] **Step 4: Commit**

```bash
git -C /home/bfranke/projects/lighthouse add src/Execution/Arguments/ArgPartitioner.php src/Execution/Arguments/SaveModel.php && git -C /home/bfranke/projects/lighthouse commit -m "Route SaveAwareArgResolver through pre-save in SaveModel"
```

---

### Task 5: Test @upsert on BelongsTo INPUT_FIELD_DEFINITION

**Goal:** Prove that `@upsert` on a BelongsTo field creates the related model and sets the FK before the parent saves.

**Files:**
- Modify: `tests/Integration/Schema/Directives/CreateDirectiveTest.php`

**Acceptance Criteria:**
- [ ] Test creates a Task with `@upsert` on a BelongsTo `user` field
- [ ] FK is set before parent save (no integrity violation)
- [ ] Related model is created and associated correctly
- [ ] Test for precedence over implicit relation detection (argument named same as relation)

**Verify:** `docker compose run --rm php vendor/bin/phpunit --filter=testUpsertBelongsToBeforeSave` → PASS

**Steps:**

- [ ] **Step 1: Write integration test**

Add to `tests/Integration/Schema/Directives/CreateDirectiveTest.php`:

```php
    public function testUpsertBelongsToBeforeSave(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
            user: User @belongsTo
        }

        type User {
            id: ID!
            name: String!
        }

        type Mutation {
            createTask(input: CreateTaskInput! @spread): Task @create
        }

        input CreateTaskInput {
            name: String!
            user: CreateUserInput @upsert
        }

        input CreateUserInput {
            id: ID
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "My task"
                user: {
                    name: "New User"
                }
            }) {
                id
                name
                user {
                    id
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTask' => [
                    'name' => 'My task',
                    'user' => [
                        'id' => '1',
                        'name' => 'New User',
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertBelongsToTakesPrecedenceOverImplicitRelation(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
            user: User @belongsTo
        }

        type User {
            id: ID!
            name: String!
        }

        type Mutation {
            createTask(input: CreateTaskInput! @spread): Task @create
        }

        input CreateTaskInput {
            name: String!
            user: CreateUserInput @upsert
        }

        input CreateUserInput {
            id: ID
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "My task"
                user: {
                    name: "Created via directive"
                }
            }) {
                id
                name
                user {
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTask' => [
                    'name' => 'My task',
                    'user' => [
                        'name' => 'Created via directive',
                    ],
                ],
            ],
        ]);
    }
```

- [ ] **Step 2: Run tests**

Run: `docker compose run --rm php vendor/bin/phpunit --filter=testUpsertBelongsTo`
Expected: PASS (both tests)

- [ ] **Step 3: Commit**

```bash
git -C /home/bfranke/projects/lighthouse add tests/Integration/Schema/Directives/CreateDirectiveTest.php && git -C /home/bfranke/projects/lighthouse commit -m "Test @upsert on BelongsTo INPUT_FIELD_DEFINITION"
```

---

### Task 6: Test @geocode custom directive (non-relation pre-save)

**Goal:** Prove that a custom `SaveAwareArgResolver` that always returns `true` can set model attributes before save without being relation-bound.

**Files:**
- Create: `tests/Utils/Directives/GeocodeDirective.php`
- Modify: `tests/Integration/Schema/Directives/CreateDirectiveTest.php`

**Acceptance Criteria:**
- [ ] `@geocode` directive implements `SaveAwareArgResolver` with `runBeforeSave() => true`
- [ ] Takes an `AddressInput` argument, sets `latitude` and `longitude` on the model
- [ ] Integration test proves attributes are persisted in a single save

**Verify:** `docker compose run --rm php vendor/bin/phpunit --filter=testGeocodePreSaveArgResolver` → PASS

**Steps:**

- [ ] **Step 1: Create GeocodeDirective test fixture**

Create `tests/Utils/Directives/GeocodeDirective.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Utils\Directives;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\SaveAwareArgResolver;

final class GeocodeDirective extends BaseDirective implements SaveAwareArgResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
        directive @geocode on INPUT_FIELD_DEFINITION
        GRAPHQL;
    }

    public function runBeforeSave(Model $model): bool
    {
        return true;
    }

    /**
     * @param  Model  $model
     * @param  ArgumentSet  $args
     */
    public function __invoke($model, $args): void
    {
        $address = $args->toArray();
        $model->setAttribute('latitude', $address['lat'] ?? 0.0);
        $model->setAttribute('longitude', $address['lng'] ?? 0.0);
    }
}
```

- [ ] **Step 2: Add migration for lat/lng columns on users table**

Check if there's an existing way to add columns in tests. The test models use the existing migrations in `tests/database/migrations/`. We need a model with lat/lng columns. The `User` model's migration is at `tests/database/migrations/2018_02_28_000000_create_testbench_users_table.php`. Rather than modifying existing migrations, use a model that can have arbitrary attributes (Users table supports `$guarded = []`).

Actually, Lighthouse tests use `$model->fill()` / `$model->forceFill()` depending on config. The test should use a table that has these columns. Let's check what columns the users table has:

Look at the users migration — if it doesn't have lat/lng we need a different approach. Since we can't modify existing migrations without risk, let's use a simpler approach: create an inline migration in the test setUp, or use a model with JSON/text columns we can repurpose.

Alternatively — the `@geocode` directive sets attributes via `setAttribute`. If the model uses `$guarded = []` and the table has the columns, it works. Since we can't guarantee lat/lng columns, let's set existing string columns on the User model instead (e.g. repurpose existing nullable columns).

Better approach: use `Task` model which has `name` and `guard_test` columns, and have geocode set a known column. But that's contrived.

Simplest: add a test migration. Lighthouse tests already have a pattern for this — add a new migration file:

Create `tests/database/migrations/2026_06_30_000000_add_geocode_columns_to_users_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->float('latitude')->nullable();
            $table->float('longitude')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('latitude', 'longitude');
        });
    }
};
```

- [ ] **Step 3: Write integration test**

Add to `tests/Integration/Schema/Directives/CreateDirectiveTest.php`:

```php
    public function testGeocodePreSaveArgResolver(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
            latitude: Float
            longitude: Float
        }

        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String!
            location: LocationInput @geocode
        }

        input LocationInput {
            lat: Float!
            lng: Float!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(input: {
                name: "Geo User"
                location: {
                    lat: 48.1351
                    lng: 11.5820
                }
            }) {
                id
                name
                latitude
                longitude
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'Geo User',
                    'latitude' => 48.1351,
                    'longitude' => 11.582,
                ],
            ],
        ]);
    }
```

- [ ] **Step 4: Run test**

Run: `docker compose run --rm php vendor/bin/phpunit --filter=testGeocodePreSaveArgResolver`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git -C /home/bfranke/projects/lighthouse add tests/Utils/Directives/GeocodeDirective.php tests/database/migrations/2026_06_30_000000_add_geocode_columns_to_users_table.php tests/Integration/Schema/Directives/CreateDirectiveTest.php && git -C /home/bfranke/projects/lighthouse commit -m "Test @geocode non-relation SaveAwareArgResolver"
```

---

### Task 7: Test SaveAwareArgResolver inside @nest (non-Model root)

**Goal:** Prove that a `SaveAwareArgResolver` inside `@nest` still runs correctly when the root is not yet a Model (falls back to post-save position).

**Files:**
- Modify: `tests/Unit/Execution/Arguments/ArgPartitionerTest.php`

**Acceptance Criteria:**
- [ ] Unit test creates a `SaveAwareArgResolver` fixture
- [ ] Passes non-Model root to `nestedArgResolvers`
- [ ] The resolver ends up in the "nested" (post-save) partition, not excluded
- [ ] No crash or error

**Verify:** `docker compose run --rm php vendor/bin/phpunit --filter=testSaveAwareArgResolverWithNonModelRoot` → PASS

**Steps:**

- [ ] **Step 1: Create test fixture**

Create `tests/Unit/Execution/Arguments/Fixtures/SaveAwareNested.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Execution\Arguments\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\SaveAwareArgResolver;

final class SaveAwareNested extends BaseDirective implements SaveAwareArgResolver
{
    public function __invoke(mixed $root, $args): void {}

    public function runBeforeSave(Model $model): bool
    {
        return true;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
        directive @saveAwareNested on INPUT_FIELD_DEFINITION
        GRAPHQL;
    }
}
```

- [ ] **Step 2: Write unit test**

Add to `tests/Unit/Execution/Arguments/ArgPartitionerTest.php`:

```php
use Tests\Unit\Execution\Arguments\Fixtures\SaveAwareNested;
```

```php
    public function testSaveAwareArgResolverWithNonModelRoot(): void
    {
        $argumentSet = new ArgumentSet();

        $regular = new Argument();
        $argumentSet->arguments['regular'] = $regular;

        $saveAware = new Argument();
        $saveAware->directives->push(new SaveAwareNested());
        $argumentSet->arguments['saveAware'] = $saveAware;

        [$nestedArgs, $regularArgs] = ArgPartitioner::nestedArgResolvers($argumentSet, null);

        $this->assertSame(
            ['regular' => $regular],
            $regularArgs->arguments,
        );

        $this->assertSame(
            ['saveAware' => $saveAware],
            $nestedArgs->arguments,
            'SaveAwareArgResolver should be in nested (post-save) set when root is not a Model',
        );
    }

    public function testSaveAwareArgResolverWithModelRoot(): void
    {
        $argumentSet = new ArgumentSet();

        $regular = new Argument();
        $argumentSet->arguments['regular'] = $regular;

        $saveAware = new Argument();
        $saveAware->directives->push(new SaveAwareNested());
        $argumentSet->arguments['saveAware'] = $saveAware;

        [$nestedArgs, $regularArgs] = ArgPartitioner::nestedArgResolvers($argumentSet, new User());

        $this->assertSame(
            ['regular' => $regular, 'saveAware' => $saveAware],
            $regularArgs->arguments,
            'SaveAwareArgResolver with runBeforeSave=true should be excluded from nested set when root is Model',
        );

        $this->assertSame(
            [],
            $nestedArgs->arguments,
        );
    }
```

- [ ] **Step 3: Run tests**

Run: `docker compose run --rm php vendor/bin/phpunit tests/Unit/Execution/Arguments/ArgPartitionerTest.php`
Expected: PASS (all tests including existing ones)

- [ ] **Step 4: Commit**

```bash
git -C /home/bfranke/projects/lighthouse add tests/Unit/Execution/Arguments/Fixtures/SaveAwareNested.php tests/Unit/Execution/Arguments/ArgPartitionerTest.php && git -C /home/bfranke/projects/lighthouse commit -m "Test SaveAwareArgResolver partitioning with Model and non-Model roots"
```

---

### Task 8: Update CHANGELOG and run full test suite

**Goal:** Document the change and confirm nothing is broken.

**Files:**
- Modify: `CHANGELOG.md`

**Acceptance Criteria:**
- [ ] CHANGELOG has entry under `## Unreleased` → `Added`
- [ ] Full test suite passes
- [ ] PHPStan passes

**Verify:** `docker compose run --rm php sh -c 'vendor/bin/phpstan && vendor/bin/phpunit'` → OK + all tests pass

**Steps:**

- [ ] **Step 1: Update CHANGELOG**

Add under `## Unreleased` in `CHANGELOG.md`:

```markdown
### Added

- `SaveAwareArgResolver` interface for directives that need control over pre/post-save timing in mutations https://github.com/nuwave/lighthouse/pull/2777
```

- [ ] **Step 2: Run full checks**

Run: `docker compose run --rm php sh -c 'vendor/bin/phpstan && vendor/bin/phpunit'`
Expected: No errors, all tests pass

- [ ] **Step 3: Run code fixer**

Run: `docker compose run --rm php vendor/bin/php-cs-fixer fix`
Expected: No changes (or only formatting fixes)

- [ ] **Step 4: Commit**

```bash
git -C /home/bfranke/projects/lighthouse add CHANGELOG.md && git -C /home/bfranke/projects/lighthouse commit -m "Add CHANGELOG entry for SaveAwareArgResolver"
```

If php-cs-fixer made changes:

```bash
git -C /home/bfranke/projects/lighthouse add -u && git -C /home/bfranke/projects/lighthouse commit -m "Apply php-cs-fixer changes"
```

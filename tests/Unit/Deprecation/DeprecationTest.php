<?php declare(strict_types=1);

namespace Tests\Unit\Deprecation;

use GraphQL\Type\Definition\Directive;
use GraphQL\Validator\DocumentValidator;
use Nuwave\Lighthouse\Deprecation\DeprecatedUsage;
use Nuwave\Lighthouse\Deprecation\DetectDeprecatedUsage;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

final class DeprecationTest extends TestCase
{
    /** @var array<string, DeprecatedUsage> */
    protected array $deprecations = [];

    protected function setUp(): void
    {
        parent::setUp();

        DetectDeprecatedUsage::handle(function (array $deprecations): void {
            $this->deprecations = $deprecations;
        });
    }

    protected function tearDown(): void
    {
        DocumentValidator::removeRule(new DetectDeprecatedUsage(static function (): void {}));

        parent::tearDown();
    }

    public function testDetectsDeprecatedFields(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @deprecated
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $deprecatedUsage = $this->deprecations['Query.foo'];
        $this->assertSame(1, $deprecatedUsage->count);
        $this->assertSame(Directive::DEFAULT_DEPRECATION_REASON, $deprecatedUsage->reason);
    }

    public function testDetectsDeprecatedFieldWithReason(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @deprecated(reason: "bar")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $deprecatedUsage = $this->deprecations['Query.foo'];
        $this->assertSame(1, $deprecatedUsage->count);
        $this->assertSame('bar', $deprecatedUsage->reason);
    }

    public function testDetectsDeprecatedFieldsMultipleTimes(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @deprecated
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
            bar: foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
                'bar' => Foo::THE_ANSWER,
            ],
        ]);

        $deprecatedUsage = $this->deprecations['Query.foo'];
        $this->assertSame(2, $deprecatedUsage->count);
        $this->assertSame(Directive::DEFAULT_DEPRECATION_REASON, $deprecatedUsage->reason);
    }

    public function testDetectsDeprecatedEnumValueUsage(): void
    {
        $this->schema = /** @lang GraphQL */ '
        enum Foo {
            A
            B @deprecated
        }

        type Query {
            foo(foo: Foo): Int
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(foo: B)
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $deprecatedUsage = $this->deprecations['Foo.B'];
        $this->assertSame(1, $deprecatedUsage->count);
        $this->assertSame(Directive::DEFAULT_DEPRECATION_REASON, $deprecatedUsage->reason);
    }

    /** @return never */
    public function testDetectsDeprecatedEnumValueUsageInVariables(): void
    {
        $this->schema = /** @lang GraphQL */ '
        enum Foo {
            A
            B @deprecated
        }

        type Query {
            foo(foo: Foo): Int
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($foo: Foo!) {
            foo(foo: $foo)
        }
        ', [
            'foo' => 'B',
        ])->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $this->markTestIncomplete('Not implemented yet');
    }

    /** @return never */
    public function testDetectsDeprecatedEnumValueUsageInResults(): void
    {
        $this->mockResolver('B');

        $this->schema = /** @lang GraphQL */ '
        enum Foo {
            A
            B @deprecated
        }

        type Query {
            foo: Foo @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => 'B',
            ],
        ]);

        $this->markTestIncomplete('Not implemented yet');
    }
}

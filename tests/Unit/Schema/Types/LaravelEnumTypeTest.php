<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Types;

use GraphQL\Utils\SchemaPrinter;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;
use PHPUnit\Framework\Constraint\Callback;
use Tests\TestCase;
use Tests\Utils\LaravelEnums\AOrB;
use Tests\Utils\LaravelEnums\LocalizedUserType;
use Tests\Utils\LaravelEnums\PartiallyDeprecated;

final class LaravelEnumTypeTest extends TestCase
{
    protected TypeRegistry $typeRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeRegistry = $this->app->make(TypeRegistry::class);
    }

    public function testMakeEnumWithCustomName(): void
    {
        $customName = 'CustomName';
        $enumType = new LaravelEnumType(AOrB::class, $customName);

        $this->assertSame($customName, $enumType->name);
    }

    public function testEnumDescription(): void
    {
        $enumType = new LaravelEnumType(LocalizedUserType::class);
        $description = $enumType->description;

        // TODO remove check when requiring bensampo/laravel-enum:6
        // @phpstan-ignore-next-line depends on the required version
        if (method_exists(LocalizedUserType::class, 'getClassDescription')) {
            $this->assertSame('Localized user type', $description);
        } else {
            $this->assertNull($description);
        }
    }

    public function testCustomDescription(): void
    {
        $enumType = new LaravelEnumType(LocalizedUserType::class);
        $values = $enumType->config['values'];

        $this->assertIsArray($values);
        $this->assertArrayHasKey('Moderator', $values);
        $this->assertSame('Localize Moderator', $values['Moderator']['description']);
    }

    public function testDeprecated(): void
    {
        $enumType = new LaravelEnumType(PartiallyDeprecated::class);

        // TODO remove check when requiring bensampo/laravel-enum:6
        // @phpstan-ignore-next-line depends on the required version
        if (method_exists(LocalizedUserType::class, 'getClassDescription')) {
            $this->assertSame(/** @lang GraphQL */ <<<GRAPHQL
"Partially deprecated"
enum PartiallyDeprecated {
  "Not"
  NOT

  "Deprecated"
  DEPRECATED @deprecated

  "Deprecated with reason"
  DEPRECATED_WITH_REASON @deprecated(reason: "some reason")
}
GRAPHQL,
                SchemaPrinter::printType($enumType),
            );
        } else {
            $this->assertSame(/** @lang GraphQL */ <<<GRAPHQL
enum PartiallyDeprecated {
  "Not"
  NOT

  "Deprecated"
  DEPRECATED @deprecated

  "Deprecated with reason"
  DEPRECATED_WITH_REASON @deprecated(reason: "some reason")
}
GRAPHQL,
                SchemaPrinter::printType($enumType),
            );
        }
    }

    public function testReceivesEnumInstanceInternally(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(bar: AOrB): Boolean @mock
        }
        GRAPHQL;

        $this->typeRegistry->register(
            new LaravelEnumType(AOrB::class),
        );

        $this->mockResolver()
            ->with(null, new Callback(static fn (array $args): bool => $args['bar'] instanceof AOrB));

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo(bar: A)
        }
        GRAPHQL);
    }

    public function testClassDoesNotExist(): void
    {
        $nonExisting = 'should be a class-string, is whatever';
        $this->expectExceptionObject(LaravelEnumType::classDoesNotExist($nonExisting));
        // @phpstan-ignore-next-line intentionally wrong
        new LaravelEnumType($nonExisting);
    }

    public function testClassMustExtendBenSampoEnumEnum(): void
    {
        $notBenSampoEnumEnum = \stdClass::class;
        $this->expectExceptionObject(LaravelEnumType::classMustExtendBenSampoEnumEnum($notBenSampoEnumEnum));
        // @phpstan-ignore-next-line intentionally wrong
        new LaravelEnumType($notBenSampoEnumEnum);
    }

    public function testEnumMustHaveKey(): void
    {
        $enumType = new LaravelEnumType(AOrB::class);

        $aWithoutKey = AOrB::A();
        $aWithoutKey->key = '';

        $this->expectExceptionObject(LaravelEnumType::enumMustHaveKey($aWithoutKey));
        $enumType->serialize($aWithoutKey);
    }
}

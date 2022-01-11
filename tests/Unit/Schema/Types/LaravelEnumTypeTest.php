<?php

namespace Tests\Unit\Schema\Types;

use GraphQL\Utils\SchemaPrinter;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;
use PHPUnit\Framework\Constraint\Callback;
use Tests\TestCase;
use Tests\Utils\LaravelEnums\AOrB;
use Tests\Utils\LaravelEnums\LocalizedUserType;
use Tests\Utils\LaravelEnums\PartiallyDeprecated;

class LaravelEnumTypeTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    public function setUp(): void
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

    public function testCustomDescription(): void
    {
        $enumType = new LaravelEnumType(LocalizedUserType::class);

        $this->assertSame('Localize Moderator', $enumType->config['values']['Moderator']['description']);
    }

    public function testDeprecated(): void
    {
        $enumType = new LaravelEnumType(PartiallyDeprecated::class);

        $this->assertSame(/** @lang GraphQL */ <<<GRAPHQL
enum PartiallyDeprecated {
  """Not"""
  NOT

  """Deprecated"""
  DEPRECATED @deprecated

  """Deprecated with reason"""
  DEPRECATED_WITH_REASON @deprecated(reason: "some reason")
}
GRAPHQL
            ,
            SchemaPrinter::printType($enumType)
        );
    }

    public function testReceivesEnumInstanceInternally(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: AOrB): Boolean @mock
        }
        ';

        $this->typeRegistry->register(
            new LaravelEnumType(AOrB::class)
        );

        $this->mockResolver()
            ->with(null, new Callback(function (array $args): bool {
                return $args['bar'] instanceof AOrB;
            }));

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(bar: A)
        }
        ');
    }
}

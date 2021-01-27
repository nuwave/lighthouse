<?php

namespace Tests\Unit\Schema\Types;

use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;
use PHPUnit\Framework\Constraint\Callback;
use Tests\TestCase;
use Tests\Utils\LaravelEnums\AOrB;
use Tests\Utils\LaravelEnums\LocalizedUserType;

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

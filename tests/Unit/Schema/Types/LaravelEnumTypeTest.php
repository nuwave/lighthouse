<?php

namespace Tests\Unit\Schema\Types;

use Tests\TestCase;
use Tests\Utils\LaravelEnums\UserType;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use PHPUnit\Framework\Constraint\Callback;
use Tests\Utils\LaravelEnums\LocalizedUserType;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;

class LaravelEnumTypeTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeRegistry = $this->app->make(TypeRegistry::class);
    }

    public function testMakeEnumWithCustomName(): void
    {
        $customName = 'CustomName';
        $enumType = new LaravelEnumType(UserType::class, $customName);

        $this->assertSame($customName, $enumType->name);
    }

    public function testCustomDescription()
    {
        $enumType = new LaravelEnumType(LocalizedUserType::class);

        $this->assertSame('Localize Moderator', $enumType->config['values']['Moderator']['description']);
    }

    public function testReceivesEnumInstanceInternally(): void
    {
        $this->schema = '
        type Query {
            foo(bar: UserType): Boolean @mock
        }
        ';

        $this->typeRegistry->register(
            new LaravelEnumType(UserType::class)
        );

        $this->mockResolver()
            ->with(null, new Callback(function (array $args): bool {
                return $args['bar'] instanceof UserType;
            }));

        $this->graphQL('
        {
            foo(bar: Administrator)
        }
        ');
    }
}

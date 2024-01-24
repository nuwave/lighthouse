<?php declare(strict_types=1);

namespace Tests\Integration;

use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\TestCase;
use Tests\Utils\Scalars\Email;

final class IntrospectionTest extends TestCase
{
    /** @var TypeRegistry */
    protected $typeRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeRegistry = $this->app->make(TypeRegistry::class);
    }

    public function testFindsTypesFromSchema(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Foo {
            bar: Int
        }
        ';

        $this->assertNotNull(
            $this->introspectType('Foo'),
        );
        $this->assertNotNull(
            $this->introspectType(RootType::QUERY),
        );

        $this->assertNull(
            $this->introspectType('Bar'),
        );
    }

    public function testFindsManuallyRegisteredTypes(): void
    {
        $this->typeRegistry->register(
            new Email(),
        );

        $this->assertNotNull(
            $this->introspectType('Email'),
        );
    }
}

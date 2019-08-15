<?php

namespace Tests\Integration;

use Tests\TestCase;
use Tests\Utils\Scalars\Email;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Illuminate\Foundation\Testing\TestResponse;

class IntrospectionTest extends TestCase
{

    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    /**
     * @var TestResponse|null
     */
    protected $introspectionResult;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeRegistry = $this->app->make(TypeRegistry::class);
    }

    /**
     * @test
     */
    public function itFindsTypesFromSchema(): void
    {
        $this->schema = '
        type Foo {
            bar: Int
        }        
        '.$this->placeholderQuery();

        $introspection = $this->introspect();

        $this->assertTrue(
            $this->isTypeNamePresent($introspection, 'Foo')
        );
        $this->assertTrue(
            $this->isTypeNamePresent($introspection, 'Query')
        );
        $this->assertFalse(
            $this->isTypeNamePresent($introspection, 'Bar')
        );
    }

    /**
     * @test
     */
    public function itFindsManuallyRegisteredTypes(): void
    {
        $this->schema = $this->placeholderQuery();
        $this->typeRegistry->register(
            new Email()
        );

        $this->assertTrue(
            $this->isTypeNamePresent($this->introspect(), 'Email')
        );
    }

    protected function isTypeNamePresent(TestResponse $introspection, string $typeName): bool
    {
        return (new Collection($introspection->jsonGet('data.__schema.types')))
            ->contains(function (array $type) use ($typeName): bool {
                return $type['name'] === $typeName;
            });
    }
}

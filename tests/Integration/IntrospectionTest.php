<?php

namespace Tests\Integration;

use Tests\TestCase;
use Tests\Utils\Scalars\Email;
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

        $this->assertNotNull(
            $this->introspectType('Foo')
        );
        $this->assertNotNull(
            $this->introspectType('Query')
        );

        $this->assertNull(
            $this->introspectType('Bar')
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

        $this->assertNotNull(
            $this->introspectType('Email')
        );
    }
}

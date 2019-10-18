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

    public function testFindsTypesFromSchema(): void
    {
        $this->schema .= '
        type Foo {
            bar: Int
        }        
        ';

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

    public function testFindsManuallyRegisteredTypes(): void
    {
        $this->typeRegistry->register(
            new Email()
        );

        $this->assertNotNull(
            $this->introspectType('Email')
        );
    }
}

<?php

namespace Nuwave\Lighthouse\Testing;

use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;

trait UsesTestSchema
{
    /**
     * The schema that Lighthouse will use.
     *
     * @var string
     */
    protected $schema;

    protected function setUpTestSchema(): void
    {
        app()->bind(
            SchemaSourceProvider::class,
            function (): TestSchemaProvider {
                return new TestSchemaProvider($this->schema);
            }
        );
    }
}

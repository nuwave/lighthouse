<?php

namespace Nuwave\Lighthouse\Testing;

use Illuminate\Container\Container;
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
        Container::getInstance()->bind(
            SchemaSourceProvider::class,
            function (): TestSchemaProvider {
                if (! isset($this->schema)) {
                    throw new \Exception('Missing test schema, provide one by setting $this->schema.');
                }

                return new TestSchemaProvider($this->schema);
            }
        );
    }
}

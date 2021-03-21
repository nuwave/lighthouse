<?php

namespace Nuwave\Lighthouse\Events;

use GraphQL\Type\Schema;

/**
 * Dispatched when php artisan lighthouse:validate-schema is called.
 *
 * Listeners should throw a descriptive error if the schema is wrong.
 */
class ValidateSchema
{
    /**
     * The final schema to validate.
     *
     * @var \GraphQL\Type\Schema
     */
    public $schema;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }
}

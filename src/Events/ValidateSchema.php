<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Events;

use GraphQL\Type\Schema;

/**
 * Dispatched when php artisan lighthouse:validate-schema is called.
 *
 * Listeners should throw a descriptive error if the schema is wrong.
 */
class ValidateSchema
{
    public function __construct(
        /**
         * The final schema to validate.
         */
        public Schema $schema,
    ) {}
}

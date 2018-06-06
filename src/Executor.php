<?php


namespace Nuwave\Lighthouse;


use Nuwave\Lighthouse\Schema\Schema;

interface Executor
{
    public function execute(Schema $schema, string $query);
}

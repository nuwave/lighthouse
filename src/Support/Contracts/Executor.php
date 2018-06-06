<?php


namespace Nuwave\Lighthouse\Support\Contracts;


use Nuwave\Lighthouse\Schema\Schema;

interface Executor
{
    public function execute(Schema $schema, string $query);
}

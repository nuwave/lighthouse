<?php


namespace Nuwave\Lighthouse;


interface Executor
{
    public function execute(Schema $schema, string $query);
}
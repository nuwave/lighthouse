<?php


namespace Nuwave\Lighthouse;


interface SchemaBuilder
{
    public function buildFromTypeLanguage(string $schema) : Schema;
}
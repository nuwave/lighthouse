<?php

namespace Tests\Unit\Schema;

use Tests\TestCase;

class PrintSchemaCommandTest extends TestCase
{
//    protected $schema = '
//    type Query {
//      foo: String
//      # Just a comment
//    }
//    ';
    protected $schema = '
    "One liner"
    type Query {
      """
      Multi
      line
      """
      foo: String
      # Just a comment
    }
    ';

    /**
     * @test
     */
    public function itPrintsDefaultSchema()
    {
        $this->artisan('lighthouse:print-schema', ['--write' => true]);
    }
}

<?php

namespace Tests\Unit\Schema\AST;

use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use PHPUnit\Framework\TestCase;

class SchemaStitcherTest extends TestCase
{
    /**
     * Set up test case.
     */
    protected function setUp()
    {
        if (! is_dir(__DIR__.'/schema')) {
            mkdir(__DIR__.'/schema');
        }

        file_put_contents(__DIR__.'/foo.graphql', '
            #import ./schema/bar.graphql
            type Foo {
                foo: String!
            }
        ');

        file_put_contents(__DIR__.'/schema/bar.graphql', '
            #import ./baz.graphql
            type Bar {
                bar: String!
            }
        ');

        file_put_contents(__DIR__.'/schema/baz.graphql', '
            type Baz {
                baz: String!
            }
        ');
    }

    /**
     * Tear down test case.
     */
    protected function tearDown()
    {
        unlink(__DIR__.'/foo.graphql');
        unlink(__DIR__.'/schema/bar.graphql');
        unlink(__DIR__.'/schema/baz.graphql');

        if (is_dir(__DIR__.'/schema')) {
            rmdir(__DIR__.'/schema');
        }
    }

    /**
     * @test
     */
    public function itConcatsSchemas()
    {
        $schema = (new SchemaStitcher(__DIR__.'/schema/baz.graphql'))->getSchemaString();
        
        $this->assertContains('type Baz', $schema);
    }

    /**
     * @test
     */
    public function itCanImportSchemas()
    {
        $schema = (new SchemaStitcher(__DIR__.'/foo.graphql'))->getSchemaString();
        
        $this->assertContains('type Foo', $schema);
        $this->assertContains('type Bar', $schema);
        $this->assertContains('type Baz', $schema);
    }
}

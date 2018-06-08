<?php

namespace Tests\Unit\Schema\AST;

use Nuwave\Lighthouse\Schema\AST\SchemaStitcher;
use PHPUnit\Framework\TestCase;

class SchemaStitcherTest extends TestCase
{
    /**
     * Schema stitcher.
     *
     * @var SchemaStitcher
     */
    protected $stitcher;

    /**
     * Set up test case.
     */
    protected function setUp()
    {
        $this->stitcher = new SchemaStitcher();

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
        $schema = $this->stitcher->stitch(__DIR__.'/schema/baz.graphql');
        $this->assertContains('type Baz', $schema);
    }

    /**
     * @test
     */
    public function itCanImportSchemas()
    {
        $schema = $this->stitcher->stitch(__DIR__.'/foo.graphql');
        $this->assertContains('type Foo', $schema);
        $this->assertContains('type Bar', $schema);
        $this->assertContains('type Baz', $schema);
    }
}

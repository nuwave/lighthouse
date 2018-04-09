<?php

namespace Tests\Unit\Schema\Utils;

use Nuwave\Lighthouse\Schema\Utils\SchemaStitcher;
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
        }');

        file_put_contents(__DIR__.'/schema/bar.graphql', '
        #import ./baz.graphql
        type Bar {
            bar: String!
        }');

        file_put_contents(__DIR__.'/schema/baz.graphql', '
        type Baz {
            baz: String!
        }');
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
    public function itStitchesLighthouseSchema()
    {
        $schema = $this->stitcher->stitch('_id');
        $hasNode = false !== strpos($schema, 'interface Node');

        $this->assertTrue($hasNode);
    }

    /**
     * @test
     */
    public function itConcatsSchemas()
    {
        $schema = $this->stitcher->stitch('_id', __DIR__.'/schema/baz.graphql');
        $hasNode = false !== strpos($schema, 'interface Node');
        $hasBaz = false !== strpos($schema, 'type Baz');

        $this->assertTrue($hasNode);
        $this->assertTrue($hasBaz);
    }

    /**
     * @test
     */
    public function itCanImportSchemas()
    {
        $schema = $this->stitcher->stitch('_id', __DIR__.'/foo.graphql');
        $hasNode = false !== strpos($schema, 'interface Node');
        $hasFoo = false !== strpos($schema, 'type Foo');
        $hasBar = false !== strpos($schema, 'type Bar');
        $hasBaz = false !== strpos($schema, 'type Baz');

        $this->assertTrue($hasNode, 'Schema does not include type Node');
        $this->assertTrue($hasFoo, 'Schema does not include type Foo');
        $this->assertTrue($hasBar, 'Schema does not include type Bar');
        $this->assertTrue($hasBaz, 'Schema does not include type Baz');
    }
}

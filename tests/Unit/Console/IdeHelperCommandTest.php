<?php

namespace Tests\Unit\Console;

use Nuwave\Lighthouse\Console\IdeHelperCommand;
use Nuwave\Lighthouse\Schema\Directives\FieldDirective;
use Tests\TestCase;

class IdeHelperCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        unlink(IdeHelperCommand::filePath());
    }
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app['config'];

        $config->set('lighthouse.namespaces.directives', [
            'Tests\\Unit\\Console',
        ]);
    }

    /**
     * @test
     */
    public function itGeneratesSchemaDirectives(): void
    {
        $expectedPath = IdeHelperCommand::filePath();
        $this->assertFileNotExists($expectedPath);

        $this->artisan('lighthouse:ide-helper');

        $this->assertFileExists($expectedPath);
        $generated = file_get_contents($expectedPath);

        $this->assertStringStartsWith(IdeHelperCommand::GENERATED_NOTICE, $generated);
        $this->assertStringEndsWith("\n", $generated);

        $this->assertStringContainsString(
            FieldDirective::definition(),
            $generated,
            'Generates definition for built-in directives'
        );
        $this->assertStringContainsString(FieldDirective::class, $generated);

        $this->assertStringContainsString(
            UnionDirective::definition(),
            $generated,
            'Overwrites definitions through custom namespaces'
        );
        $this->assertStringContainsString(UnionDirective::class, $generated);
    }
}

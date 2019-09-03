<?php

namespace Tests\Unit\Console;

use Tests\TestCase;
use Nuwave\Lighthouse\Console\IdeHelperCommand;
use Nuwave\Lighthouse\Schema\Directives\FieldDirective;

class IdeHelperCommandTest extends TestCase
{
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
        $this->artisan('lighthouse:ide-helper');

        $this->assertFileExists(IdeHelperCommand::filePath());
        $generated = file_get_contents(IdeHelperCommand::filePath());

        $this->assertStringStartsWith(IdeHelperCommand::GENERATED_NOTICE, $generated);
        $this->assertStringEndsWith("\n", $generated);

        $this->assertContains(
            FieldDirective::definition(),
            $generated,
            'Generates definition for built-in directives'
        );
        $this->assertContains(FieldDirective::class, $generated);

        $this->assertContains(
            UnionDirective::definition(),
            $generated,
            'Overwrites definitions through custom namespaces'
        );
        $this->assertContains(UnionDirective::class, $generated);
    }

    /**
     * @test
     */
    public function itDoesNotRequireCustomDirectives(): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app['config'];

        $config->set('lighthouse.namespaces.directives', [
            'Empty\\Because\\The\\User\\Has\\Not\\Created\\Custom\\Directives\\Yet',
        ]);

        $this->artisan('lighthouse:ide-helper');

        $this->assertFileExists(IdeHelperCommand::filePath());
    }
}

<?php

namespace Tests\Integration\Console;

use Nuwave\Lighthouse\Console\IdeHelperCommand;
use Nuwave\Lighthouse\Schema\Directives\FieldDirective;
use Tests\TestCase;

class IdeHelperCommandTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app['config'];

        $config->set('lighthouse.namespaces.directives', [
            // Contains an overwritten UnionDirective
            'Tests\\Integration\\Console',
            // We need to ensure this does not throw an error
            'Empty\\Because\\The\\User\\Has\\Not\\Created\\Custom\\Directives\\Yet',
        ]);
    }

    /**
     * This test is pretty slow, so we put it all in one test method.
     */
    public function testGeneratesSchemaDirectives(): void
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
}

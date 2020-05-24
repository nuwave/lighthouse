<?php

namespace Tests\Integration\Console;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Utils\SchemaPrinter;
use Nuwave\Lighthouse\Console\IdeHelperCommand;
use Nuwave\Lighthouse\Schema\Directives\FieldDirective;
use Nuwave\Lighthouse\Schema\TypeRegistry;
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
    public function testGeneratesIdeHelperFiles(): void
    {
        $typeRegistry = app(TypeRegistry::class);
        $programmaticType = new EnumType([
            'name' => 'Foo',
            'values' => [
                'BAR' => [
                    'value' => 'bar',
                ],
            ],
        ]);
        $typeRegistry->register($programmaticType);

        $this->artisan('lighthouse:ide-helper');

        /*
         * Schema directives
         */

        $this->assertFileExists(IdeHelperCommand::schemaDirectivesPath());
        $schemaDirectives = file_get_contents(IdeHelperCommand::schemaDirectivesPath());

        $this->assertStringStartsWith(IdeHelperCommand::GENERATED_NOTICE, $schemaDirectives);
        $this->assertStringEndsWith("\n", $schemaDirectives);

        $this->assertContains(
            FieldDirective::definition(),
            $schemaDirectives,
            'Generates definition for built-in directives'
        );
        $this->assertContains(FieldDirective::class, $schemaDirectives);

        $this->assertContains(
            UnionDirective::definition(),
            $schemaDirectives,
            'Overwrites definitions through custom namespaces'
        );
        $this->assertContains(UnionDirective::class, $schemaDirectives);

        /*
         * Programmatic types
         */

        $this->assertFileExists(IdeHelperCommand::programmaticTypesPath());
        $programmaticTypes = file_get_contents(IdeHelperCommand::programmaticTypesPath());

        $this->assertContains(
            SchemaPrinter::printType($programmaticType),
            $programmaticTypes,
            'Generates definitions for programmatically registered types'
        );

        /*
         * PHP Ide Helper
         */

        $this->assertFileEquals(
            __DIR__.'/../../../_ide_helper.php',
            IdeHelperCommand::phpIdeHelperPath()
        );
    }
}

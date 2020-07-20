<?php

namespace Tests\Console;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Utils\SchemaPrinter;
use Nuwave\Lighthouse\Console\IdeHelperCommand;
use Nuwave\Lighthouse\Schema\Directives\FieldDirective;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\TestCase;

class IdeHelperCommandTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app['config'];

        $config->set('lighthouse.namespaces.directives', [
            // Contains an overwritten UnionDirective
            'Tests\\Console',
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

        $schemaDirectives = \Safe\file_get_contents(IdeHelperCommand::schemaDirectivesPath());

        $this->assertStringEndsWith("\n", $schemaDirectives);

        $this->assertStringContainsString(
            FieldDirective::definition(),
            $schemaDirectives,
            'Generates definition for built-in directives'
        );
        $this->assertStringContainsString(FieldDirective::class, $schemaDirectives);

        $this->assertStringContainsString(
            UnionDirective::definition(),
            $schemaDirectives,
            'Overwrites definitions through custom namespaces'
        );
        $this->assertStringContainsString(UnionDirective::class, $schemaDirectives);

        /*
         * Programmatic types
         */

        $programmaticTypes = \Safe\file_get_contents(IdeHelperCommand::programmaticTypesPath());

        $this->assertStringContainsString(
            SchemaPrinter::printType($programmaticType),
            $programmaticTypes,
            'Generates definitions for programmatically registered types'
        );

        /*
         * PHP Ide Helper
         */

        $ideHelper = \Safe\file_get_contents(IdeHelperCommand::phpIdeHelperPath());

        $this->assertStringContainsString(
            IdeHelperCommand::OPENING_PHP_TAG.IdeHelperCommand::GENERATED_NOTICE,
            $ideHelper
        );
    }
}

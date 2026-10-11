<?php declare(strict_types=1);

namespace Tests\Console;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Console\IdeHelperCommand;
use Nuwave\Lighthouse\Schema\Directives\FieldDirective;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\TestCase;

final class IdeHelperCommandTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $config = $app->make(ConfigRepository::class);
        $config->set('lighthouse.namespaces.directives', [
            'Tests\\Console', // Contains an overwritten UnionDirective
            'Empty\\Because\\The\\User\\Has\\Not\\Created\\Custom\\Directives\\Yet', // We need to ensure this does not throw an error
        ]);
    }

    /** This test is pretty slow, so we put it all in one test method. */
    public function testGeneratesIdeHelperFiles(): void
    {
        $typeRegistry = $this->app->make(TypeRegistry::class);

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

        // Schema directives

        $schemaDirectives = \Safe\file_get_contents(IdeHelperCommand::schemaDirectivesPath());

        $this->assertStringEndsWith("\n", $schemaDirectives);

        $this->assertStringContainsString(
            FieldDirective::definition(),
            $schemaDirectives,
            'Generates definition for built-in directives',
        );
        $this->assertStringContainsString(FieldDirective::class, $schemaDirectives);

        $this->assertStringContainsString(
            UnionDirective::definition(),
            $schemaDirectives,
            'Overwrites definitions through custom namespaces',
        );
        $this->assertStringContainsString(UnionDirective::class, $schemaDirectives);

        // Programmatic types

        $programmaticTypes = \Safe\file_get_contents(IdeHelperCommand::programmaticTypesPath());

        $this->assertStringContainsString(
            SchemaPrinter::printType($programmaticType),
            $programmaticTypes,
            'Generates definitions for programmatically registered types',
        );

        // PHP Ide Helper

        $ideHelper = \Safe\file_get_contents(IdeHelperCommand::phpIdeHelperPath());

        $this->assertStringContainsString(
            IdeHelperCommand::OPENING_PHP_TAG . IdeHelperCommand::GENERATED_NOTICE,
            $ideHelper,
        );
    }
}

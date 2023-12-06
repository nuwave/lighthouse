<?php declare(strict_types=1);

namespace Tests\Console;

use Illuminate\Support\Facades\Storage;
use Nuwave\Lighthouse\Console\PrintSchemaCommand;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Tests\TestCase;

final class PrintFederationSchemaCommandTest extends TestCase
{
    protected const SCHEMA_TYPE = /** @lang GraphQL */ <<<'GRAPHQL'
type Foo @key(fields: "id") {
  id: ID! @external
  foo: String!
}

GRAPHQL;

    protected const SCHEMA_QUERY = /** @lang GraphQL */ <<<'GRAPHQL'
type Query {
  foo: Int!
}
GRAPHQL;

    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class],
        );
    }

    public function testPrintsSchemaAsGraphQLSDL(): void
    {
        $this->schema = self::SCHEMA_TYPE . self::SCHEMA_QUERY;

        $tester = $this->commandTester(new PrintSchemaCommand());
        $tester->execute(['--federation' => true]);

        $sdl = $tester->getDisplay();

        $this->assertStringContainsString(self::SCHEMA_TYPE, $sdl);
        $this->assertStringContainsString(self::SCHEMA_QUERY, $sdl);
        $this->assertStringNotContainsString('extend schema', $sdl);
    }

    public function testWritesSchema(): void
    {
        $this->schema = self::SCHEMA_TYPE . self::SCHEMA_QUERY;

        Storage::fake();
        $tester = $this->commandTester(new PrintSchemaCommand());
        $tester->execute(['--federation' => true, '--write' => true]);

        $fileContent = Storage::get(PrintSchemaCommand::GRAPHQL_FEDERATION_FILENAME);

        $this->assertIsString($fileContent);
        $this->assertStringContainsString(self::SCHEMA_TYPE, $fileContent);
        $this->assertStringContainsString(self::SCHEMA_QUERY, $fileContent);
    }
}

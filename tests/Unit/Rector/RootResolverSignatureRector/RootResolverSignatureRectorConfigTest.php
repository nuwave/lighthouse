<?php declare(strict_types=1);

namespace Tests\Unit\Rector\RootResolverSignatureRector;

use Nuwave\Lighthouse\Rector\RootResolverSignatureRector;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class RootResolverSignatureRectorConfigTest extends AbstractRectorTestCase
{
    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $rector = $this->make(RootResolverSignatureRector::class);

        $this->expectException(InvalidConfigurationException::class);
        $rector->configure($configuration);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'too many elements' => [['paramNames' => ['a', 'b', 'c', 'd', 'e']]];
        yield 'non-string element' => [['paramNames' => [42]]];
        yield 'array element' => [['paramNames' => [[]]]];
        yield 'boolean element' => [['paramNames' => [true]]];
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_rule.php';
    }
}

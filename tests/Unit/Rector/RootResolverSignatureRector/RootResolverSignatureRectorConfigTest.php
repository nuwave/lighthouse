<?php declare(strict_types=1);

namespace Tests\Unit\Rector\RootResolverSignatureRector;

use Nuwave\Lighthouse\Rector\RootResolverSignatureRector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rector\Exception\Configuration\InvalidConfigurationException;

final class RootResolverSignatureRectorConfigTest extends TestCase
{
    /**
     * @dataProvider invalidConfigurations
     *
     * @param  array<string, mixed>  $configuration
     */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $rector = (new \ReflectionClass(RootResolverSignatureRector::class))
            ->newInstanceWithoutConstructor();

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
}

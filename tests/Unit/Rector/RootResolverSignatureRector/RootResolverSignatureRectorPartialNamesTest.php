<?php declare(strict_types=1);

namespace Tests\Unit\Rector\RootResolverSignatureRector;

use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class RootResolverSignatureRectorPartialNamesTest extends AbstractRectorTestCase
{
    /** @dataProvider provideData */
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public static function provideData(): \Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/FixturePartialNames');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_rule_partial_names.php';
    }
}

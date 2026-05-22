<?php declare(strict_types=1);

namespace Tests\Unit\Support;

use Nuwave\Lighthouse\Cache\CacheDirective;
use Nuwave\Lighthouse\Cache\CacheKeyAndTags;
use Nuwave\Lighthouse\Cache\CacheServiceProvider;
use Nuwave\Lighthouse\Support\AppVersion;
use Nuwave\Lighthouse\Support\ComposerClassFinder;
use Nuwave\Lighthouse\Support\DriverManager;
use Nuwave\Lighthouse\Support\Utils;
use Tests\Console\UnionDirective;
use Tests\TestCase;

final class ComposerClassFinderTest extends TestCase
{
    public function testReturnsConcreteClassesInNamespace(): void
    {
        $classes = ComposerClassFinder::directClassesInNamespace('Nuwave\\Lighthouse\\Cache');

        $this->assertContains(CacheDirective::class, $classes);
        $this->assertContains(CacheServiceProvider::class, $classes);
    }

    public function testFiltersInterfacesAndTraits(): void
    {
        $classes = ComposerClassFinder::directClassesInNamespace('Nuwave\\Lighthouse\\Cache');

        $this->assertNotContains(CacheKeyAndTags::class, $classes, 'Interfaces must not be returned.');
    }

    public function testDoesNotRecurseIntoSubNamespaces(): void
    {
        $classes = ComposerClassFinder::directClassesInNamespace('Nuwave\\Lighthouse\\Support');

        $this->assertContains(ComposerClassFinder::class, $classes);
        $this->assertContains(Utils::class, $classes);
        $this->assertContains(AppVersion::class, $classes);
        $this->assertContains(DriverManager::class, $classes);

        foreach ($classes as $class) {
            $this->assertStringNotContainsString(
                'Nuwave\\Lighthouse\\Support\\Contracts\\',
                $class,
                'Sub-namespace Contracts must not be recursed into.',
            );
            $this->assertStringNotContainsString(
                'Nuwave\\Lighthouse\\Support\\Traits\\',
                $class,
                'Sub-namespace Traits must not be recursed into.',
            );
        }
    }

    public function testReturnsEmptyArrayForUnknownNamespace(): void
    {
        $this->assertSame([], ComposerClassFinder::directClassesInNamespace('Definitely\\Not\\A\\Real\\Namespace'));
    }

    public function testReturnsEmptyArrayForLeadingBackslash(): void
    {
        // Matches the strict behavior of `HaydenPierce\ClassFinder` in `STANDARD_MODE`,
        // which does not normalize a leading backslash.
        $this->assertSame([], ComposerClassFinder::directClassesInNamespace('\\Nuwave\\Lighthouse\\Cache'));
    }

    public function testTolerateTrailingBackslash(): void
    {
        $without = ComposerClassFinder::directClassesInNamespace('Nuwave\\Lighthouse\\Cache');
        $with = ComposerClassFinder::directClassesInNamespace('Nuwave\\Lighthouse\\Cache\\');

        sort($without);
        sort($with);

        $this->assertSame($without, $with);
    }

    public function testDiscoversClassesFromAutoloadDevPrefix(): void
    {
        $classes = ComposerClassFinder::directClassesInNamespace('Tests\\Console');

        $this->assertContains(UnionDirective::class, $classes);
    }
}

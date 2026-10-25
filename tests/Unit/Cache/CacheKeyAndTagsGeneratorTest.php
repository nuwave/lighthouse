<?php declare(strict_types=1);

namespace Tests\Unit\Cache;

use Nuwave\Lighthouse\Cache\CacheKeyAndTagsGenerator;
use PHPUnit\Framework\TestCase;

final class CacheKeyAndTagsGeneratorTest extends TestCase
{
    /** @param  array<string, mixed>  $args */
    private function key(array $args): string
    {
        return (new CacheKeyAndTagsGenerator())->key(
            user: null,
            isPrivate: false,
            parentName: 'Query',
            id: null,
            fieldName: 'foo',
            args: $args,
            path: ['foo'],
        );
    }

    public function testEncodesScalarArgs(): void
    {
        $this->assertSame('lighthouse:Query::foo:bar:"baz":first:5', $this->key(['first' => 5, 'bar' => 'baz']));
    }

    public function testEncodesArrayArgs(): void
    {
        $this->assertSame('lighthouse:Query::foo:ids:[1,2]', $this->key(['ids' => [1, 2]]));
    }

    public function testEncodesStringableArgs(): void
    {
        $value = new class implements \Stringable {
            public function __toString(): string
            {
                return 'baz';
            }
        };

        $this->assertSame('lighthouse:Query::foo:bar:baz', $this->key(['bar' => $value]));
    }

    public function testDistinguishesArgsOfDifferentTypes(): void
    {
        $keys = [
            $this->key(['arg' => false]),
            $this->key(['arg' => null]),
            $this->key(['arg' => '']),
            $this->key(['arg' => true]),
            $this->key(['arg' => 1]),
            $this->key(['arg' => 1.5]),
            $this->key(['arg' => 'null']),
            $this->key(['arg' => 'true']),
            $this->key(['arg' => 'false']),
            $this->key(['arg' => '1']),
            $this->key(['arg' => '1.5']),
            $this->key(['arg' => '[1]']),
            $this->key(['arg' => [1]]),
            $this->key(['arg' => 'a:b:c']),
            $this->key(['arg' => 'a', 'b' => 'c']),
            $this->key([]),
        ];

        $this->assertSame($keys, array_unique($keys));
    }
}

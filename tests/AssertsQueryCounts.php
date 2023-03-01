<?php declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\DB;

/**
 * This trait was taken from a package that supports fewer Laravel versions than us.
 *
 * @see https://github.com/mattiasgeniar/phpunit-query-count-assertions
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait AssertsQueryCounts
{
    protected function countQueries(?int &$count): void
    {
        DB::listen(static function () use (&$count): void {
            ++$count;
        });
    }

    protected function assertNoQueriesExecuted(\Closure $closure = null): void
    {
        if (null !== $closure) {
            self::trackQueries();

            $closure();
        }

        $this->assertQueryCountMatches(0);

        if (null !== $closure) {
            DB::flushQueryLog();
        }
    }

    protected function assertQueryCountMatches(int $count, \Closure $closure = null): void
    {
        if (null !== $closure) {
            self::trackQueries();

            $closure();
        }

        $this->assertEquals($count, self::getQueryCount());

        if (null !== $closure) {
            DB::flushQueryLog();
        }
    }

    protected function assertQueryCountLessThan(int $count, \Closure $closure = null): void
    {
        if (null !== $closure) {
            self::trackQueries();

            $closure();
        }

        $this->assertLessThan($count, self::getQueryCount());

        if (null !== $closure) {
            DB::flushQueryLog();
        }
    }

    protected function assertQueryCountGreaterThan(int $count, \Closure $closure = null): void
    {
        if (null !== $closure) {
            self::trackQueries();

            $closure();
        }

        $this->assertGreaterThan($count, self::getQueryCount());

        if (null !== $closure) {
            DB::flushQueryLog();
        }
    }

    protected static function trackQueries(): void
    {
        DB::enableQueryLog();
    }

    /** @return array<array{query: string, bindings: array<int, mixed>, time: ?float}> */
    protected static function getQueriesExecuted(): array
    {
        return DB::getQueryLog();
    }

    protected static function getQueryCount(): int
    {
        return count(self::getQueriesExecuted());
    }
}

<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        DB::listen(function ($query) use (&$count): void {
            // ignore fetch column list.
            if (Str::contains($query->sql, ['select column_name'])) {
                return;
            }

            ++$count;
        });
    }

    protected function assertNoQueriesExecuted(\Closure $closure = null): void
    {
        if ($closure) {
            self::trackQueries();

            $closure();
        }

        $this->assertQueryCountMatches(0);

        if ($closure) {
            DB::flushQueryLog();
        }
    }

    protected function assertQueryCountMatches(int $count, \Closure $closure = null): void
    {
        if ($closure) {
            self::trackQueries();

            $closure();
        }

        $this->assertEquals($count, self::getQueryCount());

        if ($closure) {
            DB::flushQueryLog();
        }
    }

    protected function assertQueryCountLessThan(int $count, \Closure $closure = null): void
    {
        if ($closure) {
            self::trackQueries();

            $closure();
        }

        $this->assertLessThan($count, self::getQueryCount());

        if ($closure) {
            DB::flushQueryLog();
        }
    }

    protected function assertQueryCountGreaterThan(int $count, \Closure $closure = null): void
    {
        if ($closure) {
            self::trackQueries();

            $closure();
        }

        $this->assertGreaterThan($count, self::getQueryCount());

        if ($closure) {
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

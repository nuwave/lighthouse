<?php declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\DB;

abstract class DBTestCase extends TestCase
{
    use AssertsQueryCounts;

    public const DEFAULT_CONNECTION = 'mysql';

    public const ALTERNATE_CONNECTION = 'alternate';

    /**
     * Indicates if migrations ran.
     */
    protected static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! static::$migrated) {
            $this->artisan('migrate:fresh', [
                '--path' => __DIR__ . '/database/migrations',
                '--realpath' => true,
            ]);

            static::$migrated = true;
        }

        // Ensure we start from a clean slate each time
        // We cannot use transactions, as they do not reset autoincrement
        $databaseName = env('LIGHTHOUSE_TEST_DB_DATABASE') ?? 'lighthouse';
        $columnName = "Tables_in_{$databaseName}";
        foreach (DB::select('SHOW TABLES') as $table) {
            DB::table($table->{$columnName})->truncate();
        }

        $this->withFactories(__DIR__ . '/database/factories');
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $config = $app->make(ConfigRepository::class);
        $config->set('database.default', self::DEFAULT_CONNECTION);
        $config->set('database.connections.' . self::DEFAULT_CONNECTION, $this->mysqlOptions());
        $config->set('database.connections.' . self::ALTERNATE_CONNECTION, $this->mysqlOptions());
    }

    /**
     * @return array<string, mixed>
     */
    protected function mysqlOptions(): array
    {
        return [
            'driver' => 'mysql',
            'database' => env('LIGHTHOUSE_TEST_DB_DATABASE', 'test'),
            'username' => env('LIGHTHOUSE_TEST_DB_USERNAME', 'root'),
            'password' => env('LIGHTHOUSE_TEST_DB_PASSWORD', ''),
            'host' => env('LIGHTHOUSE_TEST_DB_HOST', 'mysql'),
            'port' => env('LIGHTHOUSE_TEST_DB_PORT', '3306'),
            'unix_socket' => env('LIGHTHOUSE_TEST_DB_UNIX_SOCKET', null),
        ];
    }
}

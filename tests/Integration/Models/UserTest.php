<?php declare(strict_types=1);

namespace Tests\Integration\Models;

use Illuminate\Support\Facades\DB;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class UserTest extends DBTestCase
{
    public function testInsertRecordsIntoTestDB(): void
    {
        factory(User::class, 2)->create();

        $this->assertCount(2, DB::table('users')->get());
    }

    public function testRefreshesDB(): void
    {
        $this->assertCount(0, DB::table('users')->get());
    }
}

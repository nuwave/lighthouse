<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTestbenchUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            // Mostly following https://github.com/laravel/laravel/blob/master/database/migrations/2014_10_12_000000_create_users_table.php
            // but marking some fields nullable to simplify tests
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('person_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::drop('users');
    }
}

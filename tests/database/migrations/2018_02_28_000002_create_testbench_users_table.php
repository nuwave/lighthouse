<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();

            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('team_id')->nullable();

            $table->unsignedInteger('person_id')->nullable();
            $table->string('person_type')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('users');
    }
}

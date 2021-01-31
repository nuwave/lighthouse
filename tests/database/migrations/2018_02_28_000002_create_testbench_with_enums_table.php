<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchWithEnumsTable extends Migration
{
    public function up(): void
    {
        Schema::create('with_enums', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->enum('type', ['A', 'B'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::drop('users');
    }
}
